<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\Metrics;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;

/**
 * Métriques OTLP (§26.3, §26.4). Les instruments sont créés à la demande et mis en
 * cache (stables, durée de vie longue — pas un état par-requête, risque B OK).
 * SDK désactivé → meter no-op → enregistrements gratuits.
 */
final class OtelMetrics implements Metrics
{
    /** @var array<string, CounterInterface> */
    private array $counters = [];

    /** @var array<string, GaugeInterface> */
    private array $gauges = [];

    /** @var array<string, HistogramInterface> */
    private array $histograms = [];

    public function __construct(
        private readonly TelemetrySdk $telemetrySdk,
        private readonly string $instrumentationScope = 'app',
    ) {
    }

    public function counter(string $name, int $value = 1, array $attributes = []): void
    {
        ($this->counters[$name] ??= $this->meter()->createCounter($name))->add($value, $attributes);
    }

    public function gauge(string $name, float|int $value, array $attributes = []): void
    {
        ($this->gauges[$name] ??= $this->meter()->createGauge($name))->record($value, $attributes);
    }

    public function histogram(string $name, float|int $value, array $attributes = []): void
    {
        ($this->histograms[$name] ??= $this->meter()->createHistogram($name))->record($value, $attributes);
    }

    private function meter(): \OpenTelemetry\API\Metrics\MeterInterface
    {
        return $this->telemetrySdk->meter($this->instrumentationScope);
    }
}
