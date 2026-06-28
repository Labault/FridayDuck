<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * Port de publication d'événements de domaine TYPÉS sur le topic de l'édition
 * (§20, §24.5). Généralise le publisher d'énergie de la Phase 3.
 *
 * Contrat : best-effort & transient (invariant E). L'implémentation NE DOIT PAS
 * lever — un hub injoignable est JOURNALISÉ, jamais propagé : l'état durable
 * prime, le push est transient (§20.6). En Phase 6, on remplace l'implémentation
 * Mercure par l'outbox transactionnel sans toucher l'appelant ni le consommateur.
 */
interface DomainEventPublisher
{
    public function publish(\DateTimeImmutable $fridayDate, DomainEvent $domainEvent): void;
}
