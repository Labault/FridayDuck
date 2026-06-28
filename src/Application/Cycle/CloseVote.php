<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\Accessory\ResolveAccessoryWinner;

/**
 * Clôt le vote (§25.1, vendredi 14:00) en invoquant le résolveur de gagnant
 * EXISTANT (4a, invariant A). La résolution est SILENCIEUSE et idemputable (le
 * gagnant est immuable) ; l'ANNONCE est faite par {@see PublishWinner} à 14:01.
 * La clé accessory-close marque la clôture (record).
 */
final readonly class CloseVote
{
    public function __construct(
        private PrepareFridayEdition $prepareFridayEdition,
        private ResolveAccessoryWinner $resolveAccessoryWinner,
        private ProcessedMessageGuard $processedMessageGuard,
    ) {
    }

    public function close(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        // Édition + options doivent exister avant de départager.
        $this->prepareFridayEdition->prepare($fridayDate, $timezone);
        $this->resolveAccessoryWinner->resolve($fridayDate, $timezone);
        $this->processedMessageGuard->markIfFirst(CycleKey::accessoryClose($fridayDate));
    }
}
