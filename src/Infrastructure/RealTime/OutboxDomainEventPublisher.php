<?php

declare(strict_types=1);

namespace App\Infrastructure\RealTime;

use App\Application\RealTime\DomainEvent;
use App\Application\RealTime\DomainEventPublisher;
use App\Application\Telemetry\Tracer;
use App\Domain\Outbox\OutboxEntry;
use App\Domain\Outbox\OutboxEntryRepository;
use App\Domain\Shared\Clock\ClockInterface;

/**
 * Implémentation OUTBOX du port (§20.6) — Phase 6b.
 *
 * `publish()` n'émet PLUS vers Mercure : il INSÈRE une ligne d'outbox via
 * l'EntityManager courant. Appelée DANS la transaction métier (invariant A), la
 * ligne est emportée par le commit — atomique avec la mutation, ou inexistante si
 * rollback. La publication réseau est différée au relais (at-least-once, invariant
 * B/E). Le `fridayDate` devient la clé de topic ; le payload est figé ici (format
 * de fil inchangé : `{ type, …payload }`).
 *
 * Contrairement à l'ère Mercure best-effort, cette écriture PEUT lever : un échec
 * d'insertion DOIT faire rollback la mutation (atomicité), pas être avalé.
 */
final readonly class OutboxDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private OutboxEntryRepository $outboxEntryRepository,
        private ClockInterface $clock,
        private Tracer $tracer,
    ) {
    }

    public function publish(\DateTimeImmutable $fridayDate, DomainEvent $domainEvent): void
    {
        $payload = json_encode(['type' => $domainEvent->type()] + $domainEvent->payload(), \JSON_THROW_ON_ERROR);

        $this->outboxEntryRepository->add(OutboxEntry::pending(
            $fridayDate->format('Y-m-d'),
            $domainEvent->type(),
            $payload,
            $this->clock->now(),
            // Capture la trace de la requête EN COURS → le relais recoud le lien (§26.2).
            $this->tracer->currentTraceparent(),
        ));
    }
}
