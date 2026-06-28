<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use OpenTelemetry\API\Logs\LoggerInterface as OtelLoggerInterface;
use OpenTelemetry\API\Logs\NoopLoggerProvider;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeter;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Socle SDK OpenTelemetry (Phase 7a). UNE instance persistante (worker §22.2) qui
 * détient les providers Trace/Metric/Log et les exporte en OTLP/HTTP vers un
 * Collector configurable (OTEL_EXPORTER_OTLP_ENDPOINT).
 *
 * RISQUE A (non bloquant) : export par lots, file BORNÉE, timeout court, AUCUNE
 * exception propagée (init ou flush en échec → providers no-op + log unique). Le
 * {@see forceFlush()} est appelé APRÈS la réponse (kernel.terminate) → la latence
 * client n'est jamais affectée, même Collector injoignable.
 *
 * RISQUE B (pas d'accumulation worker) : file bornée + forceFlush à chaque requête.
 *
 * Endpoint vide ⇒ DÉSACTIVÉ : providers no-op, zéro surcoût.
 */
final class TelemetrySdk
{
    private bool $built = false;

    private ?TracerProviderInterface $tracerProvider = null;

    private ?MeterProviderInterface $meterProvider = null;

    private ?LoggerProviderInterface $loggerProvider = null;

    private bool $flushFailureLogged = false;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $serviceName,
        private readonly string $serviceVersion,
        private readonly string $environment,
        private readonly LoggerInterface $logger,
        private readonly float $exportTimeout = 2.0,
    ) {
    }

    public function enabled(): bool
    {
        return '' !== trim($this->endpoint);
    }

    /**
     * Construit et enregistre les providers globaux (idempotent). À appeler tôt sur
     * les chemins CLI/worker pour que l'auto-instrumentation trouve les globaux.
     */
    public function boot(): void
    {
        $this->build();
    }

    public function tracer(string $name): TracerInterface
    {
        $this->build();

        return $this->tracerProvider?->getTracer($name) ?? new NoopTracer();
    }

    public function meter(string $name): MeterInterface
    {
        $this->build();

        return $this->meterProvider?->getMeter($name) ?? new NoopMeter();
    }

    public function logger(string $name): OtelLoggerInterface
    {
        return $this->loggerProvider()->getLogger($name);
    }

    /**
     * Provider de logs (API) pour le pont Monolog → OTLP, réel ou no-op.
     */
    public function loggerProvider(): \OpenTelemetry\API\Logs\LoggerProviderInterface
    {
        $this->build();

        return $this->loggerProvider ?? new NoopLoggerProvider();
    }

    /**
     * Vide les files vers le Collector. Best-effort, jamais bloquant pour le client
     * (appelé post-réponse), jamais propagé (risque A).
     */
    public function forceFlush(): void
    {
        if (!$this->built) {
            return;
        }

        try {
            $this->tracerProvider?->forceFlush();
            $this->meterProvider?->forceFlush();
            $this->loggerProvider?->forceFlush();
        } catch (\Throwable $throwable) {
            $this->logFlushFailureOnce($throwable);
        }
    }

    public function shutdown(): void
    {
        if (!$this->built) {
            return;
        }

        try {
            $this->tracerProvider?->shutdown();
            $this->meterProvider?->shutdown();
            $this->loggerProvider?->shutdown();
        } catch (\Throwable) {
            // arrêt best-effort
        }
    }

    private function build(): void
    {
        if ($this->built) {
            return;
        }
        $this->built = true;

        if (!$this->enabled()) {
            return; // désactivé → providers no-op
        }

        try {
            $resource = $this->resource();
            $clock = ClockFactory::getDefault();
            $base = rtrim($this->endpoint, '/');

            $this->tracerProvider = new TracerProviderBuilder()
                ->addSpanProcessor(new BatchSpanProcessor(new SpanExporter($this->transport($base.'/v1/traces')), $clock))
                ->setResource($resource)
                ->build();

            $this->meterProvider = new MeterProviderBuilder()
                ->setResource($resource)
                ->addReader(new ExportingReader(new MetricExporter($this->transport($base.'/v1/metrics'))))
                ->build();

            $this->loggerProvider = new LoggerProviderBuilder()
                ->addLogRecordProcessor(new BatchLogRecordProcessor(new LogsExporter($this->transport($base.'/v1/logs')), $clock))
                ->setResource($resource)
                ->build();

            // Enregistre ces providers comme GLOBAUX → l'auto-instrumentation
            // (ext opentelemetry : Doctrine, Messenger, HttpKernel) exporte par les
            // MÊMES pipelines non bloquants (invariant 7a préservé). Auto-shutdown
            // off : c'est le worker qui pilote flush/shutdown (§22.2).
            Sdk::builder()
                ->setTracerProvider($this->tracerProvider)
                ->setMeterProvider($this->meterProvider)
                ->setLoggerProvider($this->loggerProvider)
                ->setPropagator(TraceContextPropagator::getInstance())
                ->setAutoShutdown(false)
                ->buildAndRegisterGlobal();
        } catch (\Throwable $throwable) {
            // Init échouée → on retombe en no-op, l'app n'est jamais affectée.
            $this->tracerProvider = null;
            $this->meterProvider = null;
            $this->loggerProvider = null;
            $this->logger->warning('Télémétrie OpenTelemetry désactivée (initialisation échouée).', ['exception' => $throwable]);
        }
    }

    /**
     * @return TransportInterface<'application/x-protobuf'>
     */
    private function transport(string $endpoint): TransportInterface
    {
        // protobuf/HTTP, timeout court, AUCUN retry → ne bloque pas le worker.
        return new OtlpHttpTransportFactory()->create(
            $endpoint,
            'application/x-protobuf',
            [],
            null,
            $this->exportTimeout,
            100,
            0,
        );
    }

    private function resource(): ResourceInfo
    {
        return ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create([
            'service.name' => $this->serviceName,
            'service.version' => '' !== $this->serviceVersion ? $this->serviceVersion : 'dev',
            'deployment.environment' => $this->environment,
        ])));
    }

    private function logFlushFailureOnce(\Throwable $throwable): void
    {
        if ($this->flushFailureLogged) {
            return;
        }
        $this->flushFailureLogged = true;
        $this->logger->warning('Export de télémétrie OpenTelemetry en échec (dégradation silencieuse).', ['exception' => $throwable]);
    }
}
