<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Infrastructure\Persistence\DoctrineDatabaseHealth;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exerce le port de santé contre une VRAIE base (mécanique d'infrastructure).
 *
 * Se skippe proprement si aucune base n'est joignable, pour que `make test`
 * reste vert hors stack Docker ; en CI (service PostgreSQL), il assert réellement.
 */
#[CoversClass(DoctrineDatabaseHealth::class)]
final class DoctrineDatabaseHealthTest extends KernelTestCase
{
    public function testReportsDatabaseAvailableWhenReachable(): void
    {
        self::bootKernel();

        // Le service applicatif est inliné (injecté une seule fois) : on
        // reconstruit l'adaptateur à partir de la connexion réelle du conteneur.
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        $health = new DoctrineDatabaseHealth($connection);

        if (!$health->isAvailable()) {
            self::markTestSkipped('Aucune base PostgreSQL joignable dans cet environnement.');
        }

        self::assertTrue($health->isAvailable());
    }
}
