<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\Accessory\ResolveAccessoryWinner;
use App\Application\Telemetry\Tracer;

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
        // Optionnel : autowiré en prod (span accessory.vote.close), null en test.
        private ?Tracer $tracer = null,
    ) {
    }

    public function close(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        // Édition + options doivent exister avant de départager.
        $this->prepareFridayEdition->prepare($fridayDate, $timezone);

        $closeWork = function () use ($fridayDate, $timezone): void {
            $this->resolveAccessoryWinner->resolve($fridayDate, $timezone);
        };
        if (!$this->tracer instanceof Tracer) {
            $closeWork();
        } else {
            $this->tracer->trace('accessory.vote.close', ['friday.date' => $fridayDate->format('Y-m-d')], static fn () => $closeWork());
        }

        $this->processedMessageGuard->markIfFirst(CycleKey::accessoryClose($fridayDate));
    }
}
