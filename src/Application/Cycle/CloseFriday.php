<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\RealTime\DomainEventPublisher;
use App\Application\RealTime\FridayClosed;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Persistence\Transactional;

/**
 * Ferme le vendredi (§25.1, samedi 00:00) : s'assure que l'édition existe, fait
 * progresser son STATUT → fermé (sous verrou, idempotent — un ENREGISTREMENT, pas
 * une autorité, invariant B), puis ANNONCE FridayClosed UNE FOIS (invariant E).
 */
final readonly class CloseFriday
{
    public function __construct(
        private PrepareFridayEdition $prepareFridayEdition,
        private Transactional $transactional,
        private FridayEditionRepository $fridayEditionRepository,
        private ProcessedMessageGuard $processedMessageGuard,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function close(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        $this->prepareFridayEdition->prepare($fridayDate, $timezone);

        $this->transactional->transactional(function () use ($fridayDate, $timezone): void {
            $edition = $this->fridayEditionRepository->findByFridayForUpdate($fridayDate, $timezone);
            if ($edition instanceof FridayEdition) {
                $edition->close($this->clock->now());
                $this->fridayEditionRepository->save($edition);
            }
        });

        // §25.4 — Dédup + annonce atomiques (cf. OpenFriday) : sous async+retry, un
        // échec de publication rejoue proprement, exactement-une-fois préservé. La
        // progression du STATUT (ci-dessus) est idempotente et séparée.
        $this->transactional->transactional(function () use ($fridayDate): void {
            if ($this->processedMessageGuard->markIfFirst(CycleKey::fridayClose($fridayDate))) {
                $this->domainEventPublisher->publish($fridayDate, new FridayClosed($fridayDate->format('Y-m-d')));
            }
        });
    }
}
