<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Domain\Advice\Advice;
use App\Domain\Advice\AdviceRepository;
use App\Domain\Cycle\WeeklyReport;
use App\Domain\Cycle\WeeklyReportRepository;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use App\Domain\Visitor\FridayVisitRepository;

/**
 * Génère le bilan hebdomadaire (§25.1, samedi 00:05 ; §12.5) — agrège les chiffres
 * déjà suivis (§26.4). Idempotent par UNIQUE(iso_week) : un seul bilan par semaine
 * (la clé weekly-report:<iso-week>, §25.3, est portée par la table elle-même).
 */
final readonly class GenerateWeeklyReport
{
    public function __construct(
        private FridayEditionRepository $fridayEditionRepository,
        private FridayVisitRepository $fridayVisitRepository,
        private AdviceRepository $adviceRepository,
        private WeeklyReportRepository $weeklyReportRepository,
        private IdentifierGenerator $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function generate(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        $isoWeek = $fridayDate->format('o-\WW');
        if ($this->weeklyReportRepository->findByIsoWeek($isoWeek) instanceof WeeklyReport) {
            return; // bilan déjà généré (idempotent)
        }

        $edition = $this->fridayEditionRepository->findByFriday($fridayDate, $timezone);
        if (!$edition instanceof FridayEdition) {
            return; // rien à agréger
        }

        $weeklyReport = WeeklyReport::generate(
            $this->identifierGenerator->nextIdentifier(),
            $fridayDate,
            $isoWeek,
            [
                'peakEnergy' => $edition->energy(),
                'coffeeCount' => $edition->coffeeCount(),
                'overcaffeinationCount' => $edition->overcaffeinationCount(),
                'uniqueVisitors' => $this->fridayVisitRepository->countForEdition($edition->id()),
                'winnerAccessoryCode' => $edition->winnerAccessoryCode(),
                'adviceSlug' => $this->adviceSlug($edition),
                'concerning' => $edition->concerningCount(),
                'alreadyDone' => $edition->alreadyDoneCount(),
                'takingNotes' => $edition->takingNotesCount(),
            ],
            $this->clock->now(),
        );

        try {
            $this->weeklyReportRepository->add($weeklyReport);
        } catch (ConcurrentCreationException) {
            // Course : un bilan vient d'être généré par un autre chemin — rien à faire.
        }
    }

    private function adviceSlug(FridayEdition $fridayEdition): ?string
    {
        $adviceId = $fridayEdition->adviceId();
        if (null === $adviceId) {
            return null;
        }

        $advice = $this->adviceRepository->findById($adviceId);

        return $advice instanceof Advice ? $advice->slug() : null;
    }
}
