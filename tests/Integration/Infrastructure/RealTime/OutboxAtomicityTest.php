<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\RealTime;

use App\Application\RealTime\EnergyChanged;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Persistence\DoctrineOutboxEntryRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Infrastructure\RealTime\OutboxDomainEventPublisher;
use App\Infrastructure\Telemetry\NullTracer;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Invariant A — l'écriture de l'événement est ATOMIQUE avec la mutation métier :
 * elle vit dans la transaction courante. Si celle-ci rollback, aucune ligne
 * d'outbox n'existe (l'événement n'a pas eu lieu). Hors transaction (chemin cycle),
 * la ligne est tout de même rendue durable dans sa propre transaction.
 */
#[CoversClass(OutboxDomainEventPublisher::class)]
#[CoversClass(DoctrineOutboxEntryRepository::class)]
final class OutboxAtomicityTest extends DatabaseTestCase
{
    private const string FRIDAY = '2026-07-03';

    public function testWriteInsideTransactionIsCommittedWithIt(): void
    {
        $transactional = new DoctrineTransactional($this->registry);
        $publisher = $this->publisher();

        $transactional->transactional(static function () use ($publisher): void {
            $publisher->publish(new \DateTimeImmutable(self::FRIDAY), new EnergyChanged(1, 1, 'a1'));
        });

        self::assertSame(1, $this->countPending());
    }

    public function testRollbackLeavesNoOutboxRow(): void
    {
        $transactional = new DoctrineTransactional($this->registry);
        $publisher = $this->publisher();

        try {
            $transactional->transactional(static function () use ($publisher): void {
                $publisher->publish(new \DateTimeImmutable(self::FRIDAY), new EnergyChanged(1, 1, 'a1'));

                throw new \RuntimeException('mutation métier en échec → rollback');
            });
            self::fail('La transaction aurait dû lever.');
        } catch (\RuntimeException) {
            // attendu
        }

        // L'événement n'existe pas si la mutation n'a pas été committée (invariant A).
        self::assertSame(0, $this->countPending());
    }

    public function testWriteWithoutAmbientTransactionIsDurable(): void
    {
        // Chemin cycle (annonces 6a) : aucune transaction métier ambiante → la ligne
        // est flushée dans sa propre transaction (durable, invariant D).
        $this->publisher()->publish(new \DateTimeImmutable(self::FRIDAY), new EnergyChanged(1, 1, 'a1'));

        self::assertSame(1, $this->countPending());
    }

    private function publisher(): OutboxDomainEventPublisher
    {
        return new OutboxDomainEventPublisher(
            new DoctrineOutboxEntryRepository($this->registry),
            new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00')),
            new NullTracer(),
        );
    }

    private function countPending(): int
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        return (int) $connection->fetchOne('SELECT COUNT(*) FROM outbox WHERE published_at IS NULL');
    }
}
