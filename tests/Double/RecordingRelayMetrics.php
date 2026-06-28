<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\RealTime\RelayMetrics;

/**
 * Enregistre les appels aux points d'instrumentation du relais (§26.4) — pour
 * vérifier qu'ils sont bien posés (l'export OTel viendra en Phase 7).
 */
final class RecordingRelayMetrics implements RelayMetrics
{
    public int $succeeded = 0;

    public int $failed = 0;

    /** @var list<int> */
    public array $backlogSamples = [];

    public function publishSucceeded(): void
    {
        ++$this->succeeded;
    }

    public function publishFailed(): void
    {
        ++$this->failed;
    }

    public function backlogDepth(int $depth): void
    {
        $this->backlogSamples[] = $depth;
    }
}
