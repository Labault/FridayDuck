<?php

declare(strict_types=1);

namespace App\Domain\Friday;

use App\Domain\Shared\Persistence\ConcurrentCreationException;

/**
 * Port de persistance de l'édition (§30 : interface dans le Domaine,
 * implémentation Doctrine dans l'Infrastructure).
 */
interface FridayEditionRepository
{
    public function findByFriday(\DateTimeImmutable $fridayDate, string $timezone): ?FridayEdition;

    /**
     * Insère une édition. En cas de course (UNIQUE(friday_date, timezone) déjà
     * pris par une requête concurrente), lève {@see ConcurrentCreationException}
     * — l'appelant relit alors la ligne gagnante.
     *
     * @throws ConcurrentCreationException
     */
    public function add(FridayEdition $fridayEdition): void;
}
