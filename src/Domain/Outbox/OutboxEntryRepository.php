<?php

declare(strict_types=1);

namespace App\Domain\Outbox;

/**
 * Port de persistance de l'outbox (§20.6).
 *
 * Côté écriture : {@see add()} insère la ligne dans la transaction métier en cours
 * (atomicité, invariant A). Côté relais : {@see lockPendingForEdition()} réclame
 * les lignes non publiées d'une édition de façon race-safe (verrou + SKIP LOCKED,
 * invariant B), {@see pendingFridayDates()} énumère les éditions à relayer dans
 * l'ordre, {@see save()} marque une ligne, {@see countPending()} mesure le backlog.
 */
interface OutboxEntryRepository
{
    /**
     * Persiste la ligne. Si une transaction métier est ouverte, la ligne est
     * EMPORTÉE par son commit (atomicité) ; sinon (chemin cycle, sans transaction
     * ambiante) elle est flushée dans sa propre transaction (durabilité).
     */
    public function add(OutboxEntry $outboxEntry): void;

    /**
     * Dates de vendredi ayant des lignes non publiées, dans l'ordre d'apparition
     * (plus ancienne d'abord) — pour relayer édition par édition.
     *
     * @return list<string>
     */
    public function pendingFridayDates(): array;

    /**
     * Lignes non publiées d'une édition, VERROUILLÉES (FOR UPDATE SKIP LOCKED) et
     * triées par `id` croissant (ordre d'écriture). Un autre relais qui réclame la
     * même édition obtient une liste vide (lignes sautées) → jamais de double publi.
     *
     * @return list<OutboxEntry>
     */
    public function lockPendingForEdition(string $fridayDate): array;

    public function save(OutboxEntry $outboxEntry): void;

    public function countPending(): int;
}
