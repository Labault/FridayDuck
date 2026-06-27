<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Health\DatabaseHealthInterface;
use Doctrine\DBAL\Connection;

/**
 * Implémentation Doctrine du port de santé : un ping `SELECT 1`.
 *
 * Adaptateur d'infrastructure (§30) — aucune logique métier. Toute panne de
 * connexion est considérée comme « base indisponible », jamais propagée.
 */
final readonly class DoctrineDatabaseHealth implements DatabaseHealthInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function isAvailable(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
