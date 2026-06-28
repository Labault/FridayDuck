<?php

declare(strict_types=1);

namespace App\Domain\Visitor;

use App\Domain\Shared\Persistence\ConcurrentCreationException;

interface FridayVisitRepository
{
    public function find(string $fridayEditionId, string $visitorId): ?FridayVisit;

    /**
     * @throws ConcurrentCreationException si (édition, visiteur) déjà pris (course)
     */
    public function add(FridayVisit $fridayVisit): void;

    /**
     * Met à jour last_seen_at de la visite (instruction SQL atomique).
     */
    public function touch(FridayVisit $fridayVisit, \DateTimeImmutable $now): void;

    /** Nombre de visiteurs UNIQUES d'une édition (§26.4, pour le bilan). */
    public function countForEdition(string $fridayEditionId): int;
}
