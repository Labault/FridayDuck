<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

use App\Domain\Shared\Persistence\ConcurrentCreationException;

/**
 * Port de persistance des votes (§23.6).
 */
interface AccessoryVoteRepository
{
    public function findByEditionAndVisitor(string $fridayEditionId, string $visitorId): ?AccessoryVote;

    /**
     * Insère un vote. En cas de course sur UNIQUE(friday_edition_id, visitor_id)
     * (double-soumission), lève {@see ConcurrentCreationException} — l'appelant
     * la traduit en ALREADY_VOTED.
     *
     * @throws ConcurrentCreationException
     */
    public function add(AccessoryVote $accessoryVote): void;
}
