<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Cycle\CycleStep;
use App\Application\Cycle\FridayCycle;
use App\Domain\Friday\FridayCalendar;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler des étapes de cycle. Calcule le vendredi concerné depuis l'HORLOGE
 * (les tâches du samedi visent le vendredi ÉCOULÉ), puis délègue à {@see FridayCycle}
 * — le même point d'entrée que le rattrapage. Zéro logique métier ici.
 */
#[AsMessageHandler]
final readonly class RunCycleStepHandler
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private FridayCycle $fridayCycle,
    ) {
    }

    public function __invoke(RunCycleStep $runCycleStep): void
    {
        $fridayState = $this->fridayCalendar->currentState();
        $fridayDate = match ($runCycleStep->step) {
            CycleStep::CloseFriday, CycleStep::GenerateReport => $this->fridayCalendar->mostRecentFriday(),
            default => $fridayState->fridayDate,
        };

        $this->fridayCycle->run($runCycleStep->step, $fridayDate, $fridayState->timezoneName());
    }
}
