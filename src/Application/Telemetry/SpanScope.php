<?php

declare(strict_types=1);

namespace App\Application\Telemetry;

/**
 * Poignée d'un span en cours, passée au travail tracé pour poser des attributs
 * TARDIFS (connus seulement après calcul, p. ex. l'énergie résultante). Jamais
 * d'identité brute (risque C).
 */
interface SpanScope
{
    /**
     * @param non-empty-string $key
     */
    public function setAttribute(string $key, bool|int|float|string $value): void;
}
