<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\RealTime\RelayMetrics;
use App\Application\Telemetry\Metrics;

/**
 * Remplace NullRelayMetrics (hook posé en 6b) : émet les métriques de relais
 * Mercure (§26.3) et la profondeur du backlog outbox (§26.4) via le port Metrics.
 * Le port RelayMetrics ne bouge pas — simple bascule d'alias de service.
 */
final readonly class OtelRelayMetrics implements RelayMetrics
{
    public function __construct(private Metrics $metrics)
    {
    }

    public function publishSucceeded(): void
    {
        $this->metrics->counter('mercure.publish.count');
    }

    public function publishFailed(): void
    {
        $this->metrics->counter('mercure.publish.failure');
    }

    public function backlogDepth(int $depth): void
    {
        $this->metrics->gauge('mercure.outbox.backlog', $depth);
    }
}
