<?php

declare(strict_types=1);

namespace App\Domain\Shared\Persistence;

/**
 * Levée quand une création concurrente a déjà inséré la ligne « gagnante »
 * (violation d'une contrainte d'unicité). L'appelant doit relire la ligne
 * existante plutôt que de réessayer l'insertion (idempotence race-safe, §25.2).
 */
final class ConcurrentCreationException extends \RuntimeException
{
}
