<?php

declare(strict_types=1);

namespace App\Domain\Advice;

use App\Domain\Shared\Persistence\ConcurrentCreationException;

/**
 * Port de persistance des réactions (§23.8).
 */
interface AdviceReactionRepository
{
    public function findByEditionAndVisitor(string $fridayEditionId, string $visitorId): ?AdviceReaction;

    /**
     * Insère une réaction. Course sur UNIQUE(friday_edition_id, visitor_id) →
     * {@see ConcurrentCreationException} (le verrou d'édition la prévient en
     * pratique).
     *
     * @throws ConcurrentCreationException
     */
    public function add(AdviceReaction $adviceReaction): void;

    /** Persiste un changement de réaction (sous le verrou d'édition). */
    public function save(AdviceReaction $adviceReaction): void;
}
