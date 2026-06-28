<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\Accessory\ResolveAccessoryWinner;

/**
 * Prépare l'agrégat du bilan (§25.1, vendredi 23:55) : s'assure que l'édition est
 * prête et que le gagnant est figé, pour que {@see GenerateWeeklyReport} (samedi)
 * agrège des chiffres complets. Silencieux, idempotent (résolveurs existants).
 */
final readonly class PrepareReport
{
    public function __construct(
        private PrepareFridayEdition $prepareFridayEdition,
        private ResolveAccessoryWinner $resolveAccessoryWinner,
    ) {
    }

    public function prepare(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        $this->prepareFridayEdition->prepare($fridayDate, $timezone);
        $this->resolveAccessoryWinner->resolve($fridayDate, $timezone);
    }
}
