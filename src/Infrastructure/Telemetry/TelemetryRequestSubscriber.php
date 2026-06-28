<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\Metrics;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Span RACINE par requête HTTP (§26.2/26.3) + discipline worker (§22.2, risque B).
 *
 * Ouvre un span serveur à la requête (parent des spans métier via le contexte
 * actif), le clôt et DÉTACHE le scope à `kernel.finish_request` (qui s'exécute
 * TOUJOURS, même sur exception → pas de scope orphelin, pas d'accumulation), puis
 * force le flush du SDK à `kernel.terminate` — APRÈS l'envoi de la réponse, donc
 * SANS impacter la latence client même Collector down (risque A).
 *
 * Quand la télémétrie est désactivée (endpoint vide), tout est court-circuité :
 * aucun span, aucun scope, aucun surcoût.
 */
final class TelemetryRequestSubscriber implements EventSubscriberInterface
{
    private ?SpanInterface $span = null;

    private ?ScopeInterface $scope = null;

    private int $startedAtNanos = 0;

    public function __construct(
        private readonly TelemetrySdk $telemetrySdk,
        private readonly Metrics $metrics,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 4096],
            KernelEvents::RESPONSE => ['onResponse', -4096],
            KernelEvents::EXCEPTION => ['onException', 0],
            KernelEvents::FINISH_REQUEST => ['onFinishRequest', -4096],
            KernelEvents::TERMINATE => ['onTerminate', -4096],
        ];
    }

    public function onRequest(RequestEvent $requestEvent): void
    {
        if (!$requestEvent->isMainRequest() || !$this->telemetrySdk->enabled()) {
            return;
        }

        $method = $requestEvent->getRequest()->getMethod();
        $this->startedAtNanos = hrtime(true);
        $this->span = $this->telemetrySdk->tracer('app')
            ->spanBuilder('HTTP '.$method)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes([
                'http.request.method' => $method,
                'url.path' => $requestEvent->getRequest()->getPathInfo(),
            ])
            ->startSpan();
        $this->scope = $this->span->activate();
    }

    public function onResponse(ResponseEvent $responseEvent): void
    {
        if (!$responseEvent->isMainRequest() || !$this->span instanceof SpanInterface) {
            return;
        }

        $request = $responseEvent->getRequest();
        $route = $request->attributes->get('_route');
        $status = $responseEvent->getResponse()->getStatusCode();
        $this->span->setAttribute('http.response.status_code', $status);

        $attributes = ['http.request.method' => $request->getMethod(), 'http.response.status_code' => $status];
        if (\is_string($route) && '' !== $route) {
            $this->span->setAttribute('http.route', $route);
            $attributes['http.route'] = $route;
        }

        $this->metrics->counter('http.server.request.count', 1, $attributes);
        if (0 !== $this->startedAtNanos) {
            $this->metrics->histogram('http.server.request.duration', (hrtime(true) - $this->startedAtNanos) / 1_000_000_000, $attributes);
        }
    }

    public function onException(ExceptionEvent $exceptionEvent): void
    {
        if (!$exceptionEvent->isMainRequest() || !$this->span instanceof SpanInterface) {
            return;
        }

        $this->span->recordException($exceptionEvent->getThrowable());
        $this->span->setStatus(StatusCode::STATUS_ERROR, $exceptionEvent->getThrowable()->getMessage());
    }

    public function onFinishRequest(FinishRequestEvent $finishRequestEvent): void
    {
        if (!$finishRequestEvent->isMainRequest()) {
            return;
        }

        // Détache TOUJOURS (finish_request s'exécute même sur exception) → aucun
        // scope orphelin entre deux requêtes du worker (risque B).
        $this->scope?->detach();
        $this->span?->end();
        $this->span = null;
        $this->scope = null;
        $this->startedAtNanos = 0;
    }

    public function onTerminate(TerminateEvent $terminateEvent): void
    {
        if (!$terminateEvent->isMainRequest() || !$this->telemetrySdk->enabled()) {
            return;
        }

        // Mémoire du worker (§26.1/26.3) puis vidage best-effort POST-réponse :
        // jamais bloquant pour le client, même Collector injoignable (risque A).
        $this->metrics->gauge('worker.memory.bytes', memory_get_usage(true));
        $this->telemetrySdk->forceFlush();
    }
}
