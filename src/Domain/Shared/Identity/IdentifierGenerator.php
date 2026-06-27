<?php

declare(strict_types=1);

namespace App\Domain\Shared\Identity;

/**
 * Port de génération d'identifiants techniques (§22 : symfony/uid).
 *
 * Le Domaine manipule des identifiants opaques (chaînes) ; la fabrique concrète
 * (ULID) vit dans l'Infrastructure, pour que le Domaine reste PHP pur.
 */
interface IdentifierGenerator
{
    public function nextIdentifier(): string;
}
