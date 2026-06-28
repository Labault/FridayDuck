<?php

declare(strict_types=1);

namespace App\Application\Telemetry;

/**
 * Port de métriques (§26.3, §26.4). PHP pur. Émises AUX MUTATIONS existantes
 * (énergie sur recordCoffee, etc.), jamais dans un chemin séparé.
 *
 * Comme {@see Tracer} : non bloquant et sans donnée perso (risques A/C). Les
 * attributs sont des dimensions de FAIBLE cardinalité (état, type), jamais
 * d'identité.
 */
interface Metrics
{
    /**
     * @param non-empty-string                               $name
     * @param array<non-empty-string, bool|int|float|string> $attributes
     */
    public function counter(string $name, int $value = 1, array $attributes = []): void;

    /**
     * @param non-empty-string                               $name
     * @param array<non-empty-string, bool|int|float|string> $attributes
     */
    public function gauge(string $name, float|int $value, array $attributes = []): void;

    /**
     * @param non-empty-string                               $name
     * @param array<non-empty-string, bool|int|float|string> $attributes
     */
    public function histogram(string $name, float|int $value, array $attributes = []): void;
}
