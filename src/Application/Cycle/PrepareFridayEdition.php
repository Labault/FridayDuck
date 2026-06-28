<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\Accessory\ResolveAccessoryOptions;
use App\Application\Advice\ResolveAdvice;
use App\Application\Friday\ResolveCurrentFridayEdition;

/**
 * Prépare une édition à venir (§25.1, jeudi 23:55) en INVOQUANT les résolveurs
 * EXISTANTS (invariant A) : édition (2a-i), 3 options (4a), conseil (5). SILENCIEUX
 * et idempotent — converge avec la résolution paresseuse du chemin requête (§25.2).
 */
final readonly class PrepareFridayEdition
{
    public function __construct(
        private ResolveCurrentFridayEdition $resolveCurrentFridayEdition,
        private ResolveAccessoryOptions $resolveAccessoryOptions,
        private ResolveAdvice $resolveAdvice,
    ) {
    }

    public function prepare(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        $fridayEdition = $this->resolveCurrentFridayEdition->resolve($fridayDate, $timezone);
        $this->resolveAccessoryOptions->resolve($fridayEdition->id(), $fridayDate);
        $this->resolveAdvice->resolve($fridayDate, $timezone);
    }
}
