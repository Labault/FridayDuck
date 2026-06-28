<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Application\Telemetry\Metrics;

/**
 * Métriques enregistreuses : capturent chaque mesure (type, nom, valeur, attributs)
 * pour asserter les compteurs/jauges métier et l'absence de PII, sans SDK.
 */
final class RecordingMetrics implements Metrics
{
    /** @var list<array{type: string, name: string, value: float|int, attributes: array<string, bool|int|float|string>}> */
    public array $records = [];

    public function counter(string $name, int $value = 1, array $attributes = []): void
    {
        $this->records[] = ['type' => 'counter', 'name' => $name, 'value' => $value, 'attributes' => $attributes];
    }

    public function gauge(string $name, float|int $value, array $attributes = []): void
    {
        $this->records[] = ['type' => 'gauge', 'name' => $name, 'value' => $value, 'attributes' => $attributes];
    }

    public function histogram(string $name, float|int $value, array $attributes = []): void
    {
        $this->records[] = ['type' => 'histogram', 'name' => $name, 'value' => $value, 'attributes' => $attributes];
    }

    public function counterTotal(string $name): int
    {
        $total = 0;
        foreach ($this->records as $record) {
            if ('counter' === $record['type'] && $record['name'] === $name) {
                $total += (int) $record['value'];
            }
        }

        return $total;
    }

    public function lastGauge(string $name): float|int|null
    {
        $value = null;
        foreach ($this->records as $record) {
            if ('gauge' === $record['type'] && $record['name'] === $name) {
                $value = $record['value'];
            }
        }

        return $value;
    }

    /**
     * @return list<array{type: string, name: string, value: float|int, attributes: array<string, bool|int|float|string>}>
     */
    public function named(string $name): array
    {
        return array_values(array_filter($this->records, static fn (array $record): bool => $record['name'] === $name));
    }
}
