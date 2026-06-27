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
     * Charge l'édition en VERROU d'écriture (SELECT … FOR UPDATE) — à n'appeler
     * que dans une transaction. Ce verrou sérialise quota et énergie (§8 / D).
     */
    public function findByFridayForUpdate(\DateTimeImmutable $fridayDate, string $timezone): ?FridayEdition;

    /**
     * Persiste les mutations d'une édition managée (énergie, compteurs, version).
     */
    public function save(FridayEdition $fridayEdition): void;

    /**
     * Insère une édition. En cas de course (UNIQUE(friday_date, timezone) déjà
     * pris par une requête concurrente), lève {@see ConcurrentCreationException}
     * — l'appelant relit alors la ligne gagnante.
     *
     * @throws ConcurrentCreationException
     */
    public function add(FridayEdition $fridayEdition): void;
}
