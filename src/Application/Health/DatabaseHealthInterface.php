<?php

declare(strict_types=1);

namespace App\Application\Health;

/**
 * Port de santé infrastructure : indique si le datastore répond.
 *
 * Défini dans la couche Application pour que la Présentation interroge la base
 * SANS dépendre de Doctrine (§30). L'implémentation vit dans Infrastructure.
 */
interface DatabaseHealthInterface
{
    public function isAvailable(): bool;
}
