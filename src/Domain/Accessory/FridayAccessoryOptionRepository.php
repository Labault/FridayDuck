<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

use App\Domain\Shared\Persistence\ConcurrentCreationException;

/**
 * Port de persistance des options de vote d'une édition (§23.5).
 */
interface FridayAccessoryOptionRepository
{
    /**
     * Options d'une édition, triées par display_order.
     *
     * @return list<FridayAccessoryOption>
     */
    public function findByEdition(string $fridayEditionId): array;

    /**
     * Insère les options du jour. En cas de course (UNIQUE(friday_edition_id,
     * accessory_id) déjà pris par une requête concurrente), lève
     * {@see ConcurrentCreationException} — l'appelant relit l'ensemble gagnant.
     *
     * @param list<FridayAccessoryOption> $options
     *
     * @throws ConcurrentCreationException
     */
    public function addAll(array $options): void;

    /**
     * Persiste l'incrément de vote_count d'une option managée (sous verrou édition).
     */
    public function save(FridayAccessoryOption $fridayAccessoryOption): void;
}
