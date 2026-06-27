<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Socle des tests d'intégration touchant la base (env test → base `app_test`).
 *
 * Se skippe proprement si la base n'est pas joignable/migrée, pour garder
 * `make test` vert hors stack Docker ; en CI (service PostgreSQL + migrations),
 * les assertions s'exécutent réellement. Chaque test démarre sur des tables vides.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected Connection $connection;

    protected ManagerRegistry $registry;

    protected function setUp(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        $registry = self::getContainer()->get('doctrine');
        \assert($registry instanceof ManagerRegistry);
        $this->registry = $registry;

        try {
            $this->connection->executeStatement(
                'TRUNCATE coffee_contribution, friday_visit, friday_edition, anonymous_visitor RESTART IDENTITY CASCADE',
            );
        } catch (\Throwable $exception) {
            self::markTestSkipped('Base de test indisponible ou non migrée : '.$exception->getMessage());
        }
    }

    protected function countRows(string $table): int
    {
        // Connexion fraîche : après une violation d'unicité, le gestionnaire est
        // réinitialisé (la course édition en dépend).
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        return (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table);
    }
}
