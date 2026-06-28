<?php

declare(strict_types=1);

namespace App\Application\Cycle;

/**
 * Aiguilleur des étapes de cycle — point d'entrée UNIQUE partagé par le Scheduler
 * (message proactif) et le rattrapage (`app:friday:repair`). Les deux chemins
 * CONVERGENT par ce code, donc par les mêmes résolveurs idempotents (invariants A/D).
 */
final readonly class FridayCycle
{
    public function __construct(
        private PrepareFridayEdition $prepareFridayEdition,
        private OpenFriday $openFriday,
        private CloseVote $closeVote,
        private PublishWinner $publishWinner,
        private PrepareReport $prepareReport,
        private CloseFriday $closeFriday,
        private GenerateWeeklyReport $generateWeeklyReport,
    ) {
    }

    public function run(CycleStep $cycleStep, \DateTimeImmutable $fridayDate, string $timezone): void
    {
        match ($cycleStep) {
            CycleStep::PrepareEdition => $this->prepareFridayEdition->prepare($fridayDate, $timezone),
            CycleStep::PublishFridayOpened => $this->openFriday->open($fridayDate, $timezone),
            CycleStep::CloseVote => $this->closeVote->close($fridayDate, $timezone),
            CycleStep::PublishWinner => $this->publishWinner->publish($fridayDate, $timezone),
            CycleStep::PrepareReport => $this->prepareReport->prepare($fridayDate, $timezone),
            CycleStep::CloseFriday => $this->closeFriday->close($fridayDate, $timezone),
            CycleStep::GenerateReport => $this->generateWeeklyReport->generate($fridayDate, $timezone),
        };
    }
}
