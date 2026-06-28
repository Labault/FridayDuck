<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\Metrics;

/**
 * Métriques neutres (OTel désactivé ou indisponible) : aucun enregistrement.
 */
final class NullMetrics implements Metrics
{
    public function counter(string $name, int $value = 1, array $attributes = []): void
    {
    }

    public function gauge(string $name, float|int $value, array $attributes = []): void
    {
    }

    public function histogram(string $name, float|int $value, array $attributes = []): void
    {
    }
}
