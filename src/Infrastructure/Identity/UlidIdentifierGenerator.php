<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Shared\Identity\IdentifierGenerator;
use Symfony\Component\Uid\Ulid;

/**
 * Implémentation ULID du port d'identifiants (§22) — triable, sans aller-retour
 * base. Le Domaine ne voit qu'une chaîne (26 caractères en base 32 Crockford).
 */
final class UlidIdentifierGenerator implements IdentifierGenerator
{
    public function nextIdentifier(): string
    {
        return new Ulid()->toBase32();
    }
}
