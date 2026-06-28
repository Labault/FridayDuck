<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Port de lecture du catalogue d'accessoires (§30 : interface au Domaine).
 */
interface AccessoryRepository
{
    /**
     * Catalogue ACTIF, dans un ordre stable (par code) — la sélection des options
     * applique ensuite l'ordre déterministe seedé par la date (§10.4).
     *
     * @return list<Accessory>
     */
    public function findActive(): array;

    public function findByCode(string $code): ?Accessory;

    /**
     * @param list<string> $ids
     *
     * @return list<Accessory>
     */
    public function findByIds(array $ids): array;
}
