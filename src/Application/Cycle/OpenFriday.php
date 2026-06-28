<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\RealTime\DomainEventPublisher;
use App\Application\RealTime\FridayOpened;

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
    ) {
    }

    public function open(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        $this->prepareFridayEdition->prepare($fridayDate, $timezone);

        if ($this->processedMessageGuard->markIfFirst(CycleKey::fridayOpen($fridayDate))) {
            $this->domainEventPublisher->publish($fridayDate, new FridayOpened($fridayDate->format('Y-m-d')));
        }
    }
}
