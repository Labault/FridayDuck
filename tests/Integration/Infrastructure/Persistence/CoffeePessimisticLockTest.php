<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\Friday\FridayEdition;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Preuve déterministe que le verrou PESSIMISTIC_WRITE (SELECT … FOR UPDATE)
 * exclut mutuellement deux accès concurrents à la même édition (invariant D) :
 * une seconde connexion en FOR UPDATE NOWAIT échoue tant que le verrou est tenu.
 */
#[CoversNothing]
final class CoffeePessimisticLockTest extends DatabaseTestCase
{
    public function testForUpdateLockExcludesConcurrentAccess(): void
    {
        $editionId = (new UlidIdentifierGenerator())->nextIdentifier();
        (new DoctrineFridayEditionRepository($this->registry))->add(FridayEdition::open(
            $editionId,
            new \DateTimeImmutable('2026-07-03'),
            'Europe/Paris',
            100,
            new \DateTimeImmutable('2026-07-03T10:00:00+02:00'),
        ));

        $manager = $this->registry->getManager();
        \assert($manager instanceof EntityManagerInterface);

        $manager->beginTransaction();
        try {
            $locked = $manager->find(FridayEdition::class, $editionId, LockMode::PESSIMISTIC_WRITE);
            self::assertInstanceOf(FridayEdition::class, $locked);

            // Connexion SÉPARÉE : NOWAIT échoue immédiatement car la ligne est verrouillée.
            $second = DriverManager::getConnection($manager->getConnection()->getParams());
            try {
                $second->executeQuery(
                    'SELECT id FROM friday_edition WHERE id = :id FOR UPDATE NOWAIT',
                    ['id' => $editionId],
                );
                self::fail('La seconde connexion a obtenu le verrou : exclusion mutuelle rompue.');
            } catch (DbalException $exception) {
                self::assertStringContainsStringIgnoringCase('lock', $exception->getMessage());
            } finally {
                $second->close();
            }
        } finally {
            $manager->rollback();
        }
    }
}
