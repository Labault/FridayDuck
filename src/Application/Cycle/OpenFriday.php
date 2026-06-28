<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\RealTime\DomainEventPublisher;
use App\Application\RealTime\FridayOpened;
use App\Domain\Shared\Persistence\Transactional;

/**
 * Ouvre le vendredi (§25.1, vendredi 00:00) : s'assure que l'édition est préparée
 * puis ANNONCE FridayOpened UNE FOIS (invariant E, dédup par clé). L'annonce vient
 * du Scheduler/rattrapage, jamais de la résolution paresseuse.
 */
final readonly class OpenFriday
{
    public function __construct(
        private PrepareFridayEdition $prepareFridayEdition,
        private ProcessedMessageGuard $processedMessageGuard,
        private DomainEventPublisher $domainEventPublisher,
        private Transactional $transactional,
    ) {
    }

    public function open(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        $this->prepareFridayEdition->prepare($fridayDate, $timezone);

        // §25.4 — Marque de dédup ET écriture outbox de l'annonce dans UNE
        // transaction (atomiques). Sous async+retry, un échec de l'annonce fait
        // rollback la marque → le rejeu RÉ-essaie au lieu de sauter une annonce
        // perdue ; un échec persistant finit en file d'échec. Exactement-une-fois
        // préservé : marque et annonce committent ensemble, ou pas du tout.
        $this->transactional->transactional(function () use ($fridayDate): void {
            if ($this->processedMessageGuard->markIfFirst(CycleKey::fridayOpen($fridayDate))) {
                $this->domainEventPublisher->publish($fridayDate, new FridayOpened($fridayDate->format('Y-m-d')));
            }
        });
    }
}
