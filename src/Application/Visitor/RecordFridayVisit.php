<?php

declare(strict_types=1);

namespace App\Application\Visitor;

use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use App\Domain\Visitor\FridayVisit;
use App\Domain\Visitor\FridayVisitRepository;

/**
 * Trace la visite d'un visiteur à une édition (§23.9) : crée la ligne au premier
 * passage, met à jour last_seen_at ensuite. Upsert idempotent et race-safe via
 * UNIQUE(friday_edition_id, visitor_id).
 */
final readonly class RecordFridayVisit
{
    public function __construct(
        private FridayVisitRepository $fridayVisitRepository,
        private IdentifierGenerator $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function record(string $fridayEditionId, string $visitorId): void
    {
        $existing = $this->fridayVisitRepository->find($fridayEditionId, $visitorId);
        if ($existing instanceof FridayVisit) {
            $this->fridayVisitRepository->touch($existing, $this->clock->now());

            return;
        }

        $fridayVisit = FridayVisit::start(
            $this->identifierGenerator->nextIdentifier(),
            $fridayEditionId,
            $visitorId,
            $this->clock->now(),
        );

        try {
            $this->fridayVisitRepository->add($fridayVisit);
        } catch (ConcurrentCreationException) {
            $winner = $this->fridayVisitRepository->find($fridayEditionId, $visitorId);
            if ($winner instanceof FridayVisit) {
                $this->fridayVisitRepository->touch($winner, $this->clock->now());
            }
        }
    }
}
