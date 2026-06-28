<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * Port de publication d'événements de domaine TYPÉS sur le topic de l'édition
 * (§20, §24.5). Généralise le publisher d'énergie de la Phase 3.
 *
 * Contrat (Phase 6b) : l'implémentation ÉCRIT l'événement dans l'OUTBOX, DANS la
 * transaction métier courante (atomicité, §20.6 / invariant A) ; un relais le
 * publie ensuite sur Mercure (at-least-once). Appelée DEPUIS le service métier
 * (dans sa transaction), pas en post-commit. Contrairement à l'ère Mercure
 * best-effort, l'écriture PEUT lever : un échec doit faire rollback la mutation
 * (l'événement n'existe pas sans elle), il n'est plus « loggé et avalé ».
 */
interface DomainEventPublisher
{
    public function publish(\DateTimeImmutable $fridayDate, DomainEvent $domainEvent): void;
}
