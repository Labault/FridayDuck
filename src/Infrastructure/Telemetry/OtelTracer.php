<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\Tracer;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;

/**
 * Traceur OTLP (§26.2). Chaque span devient enfant du span actif (contexte courant)
 * → la hiérarchie requête → opérations métier se construit naturellement. Aucune
 * I/O réseau ici : les spans rejoignent la file du BatchSpanProcessor (exportée au
 * flush). Quand le SDK est désactivé, {@see TelemetrySdk::tracer()} renvoie un
 * tracer no-op et tout devient gratuit.
 */
final readonly class OtelTracer implements Tracer
{
    public function __construct(
        private TelemetrySdk $telemetrySdk,
        private string $instrumentationScope = 'app',
    ) {
    }

    public function trace(string $name, array $attributes, callable $work): mixed
    {
        $span = $this->telemetrySdk->tracer($this->instrumentationScope)
            ->spanBuilder($name)
            ->setAttributes($attributes)
            ->startSpan();

        return $this->runInSpan($span, $work);
    }

    public function currentTraceparent(): ?string
    {
        if (!Span::getCurrent()->getContext()->isValid()) {
            return null;
        }

        $carrier = [];
        TraceContextPropagator::getInstance()->inject($carrier, null, Context::getCurrent());

        return \is_string($carrier['traceparent'] ?? null) ? $carrier['traceparent'] : null;
    }

    public function traceLinkedTo(string $name, ?string $traceparent, array $attributes, callable $work): mixed
    {
        $parent = null !== $traceparent
            ? TraceContextPropagator::getInstance()->extract(['traceparent' => $traceparent])
            : Context::getCurrent();

        $span = $this->telemetrySdk->tracer($this->instrumentationScope)
            ->spanBuilder($name)
            ->setParent($parent)
            ->setAttributes($attributes)
            ->startSpan();

        return $this->runInSpan($span, $work);
    }

    /**
     * @template T
     *
     * @param callable(\App\Application\Telemetry\SpanScope): T $work
     *
     * @return T
     */
    private function runInSpan(SpanInterface $span, callable $work): mixed
    {
        $scope = $span->activate();

        try {
            return $work(new OtelSpanScope($span));
        } catch (\Throwable $throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());

            throw $throwable;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
