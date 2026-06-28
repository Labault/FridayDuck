<?php

declare(strict_types=1);

namespace App\Domain\Advice;

/**
 * Port de lecture du catalogue de conseils (§30 : interface au Domaine).
 */
interface AdviceRepository
{
    /**
     * Catalogue ACTIF, ordre stable (par slug) — la sélection applique ensuite
     * l'ordre déterministe seedé par la date (§10.4).
     *
     * @return list<Advice>
     */
    public function findActive(): array;

    public function findById(string $id): ?Advice;
}
