<?php

declare(strict_types=1);

namespace App\Infrastructure\RealTime;

use App\Application\RealTime\RelayMetrics;

/**
 * Implémentation neutre des points d'instrumentation du relais (hooks sans export).
 * La Phase 7 la remplacera par un enregistreur OTel.
 */
final class NullRelayMetrics implements RelayMetrics
{
    public function publishSucceeded(): void
    {
    }

    public function publishFailed(): void
    {
    }

    public function backlogDepth(int $depth): void
    {
    }
}
