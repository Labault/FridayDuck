<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Coffee;

use App\Application\Coffee\CoffeeOutcomeStatus;
use App\Application\Coffee\ServeCoffee;
use App\Application\Coffee\ServeCoffeeHandler;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Infrastructure\Persistence\DoctrineCoffeeContributionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineOutboxEntryRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Infrastructure\RealTime\OutboxDomainEventPublisher;
use App\Infrastructure\Telemetry\NullMetrics;
use App\Infrastructure\Telemetry\NullTracer;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Phase 6b — l'événement temps réel n'est plus poussé en synchrone vers Mercure :
 * il est ÉCRIT dans l'outbox, DANS la transaction du café (invariant A), et
 * seulement sur acceptation RÉELLE. Un relais le publiera (invariant B).
 */
#[CoversClass(ServeCoffeeHandler::class)]
#[CoversClass(ServeCoffee::class)]
#[CoversClass(OutboxDomainEventPublisher::class)]
final class ServeCoffeeHandlerPublishTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';
    private const string HASH = 'a-visitor-hash';

    public function testRealAcceptanceWritesOneUnpublishedOutboxRow(): void
    {
        $outcome = $this->handler()->handle(self::HASH, 'action-1');

        self::assertSame(CoffeeOutcomeStatus::Served, $outcome->status);

        $rows = $this->outboxRows();
        self::assertCount(1, $rows);
        self::assertSame('ENERGY_CHANGED', $rows[0]['type']);
        self::assertSame('2026-07-03', $rows[0]['friday_date']);
        self::assertNull($rows[0]['published_at']); // pas encore relayé

        $payload = json_decode($rows[0]['payload'], true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload));
        self::assertSame('ENERGY_CHANGED', $payload['type']);
        self::assertSame(1, $payload['energy']);
        self::assertSame(1, $payload['energyVersion']);
        self::assertSame('action-1', $payload['actionId']);
    }

    public function testIdempotentReplayWritesNoSecondRow(): void
    {
        $handler = $this->handler();

        $handler->handle(self::HASH, 'same-key'); // acceptation → 1 ligne
        $handler->handle(self::HASH, 'same-key'); // rejeu → aucune nouvelle ligne

        self::assertCount(1, $this->outboxRows());
    }

    public function testQuotaRejectionWritesNoRow(): void
    {
        $handler = $this->handler();

        $handler->handle(self::HASH, 'a1');
        $handler->handle(self::HASH, 'a2');
        $handler->handle(self::HASH, 'a3');
        $outcome = $handler->handle(self::HASH, 'a4'); // 4e → quota

        self::assertSame(CoffeeOutcomeStatus::LimitReached, $outcome->status);
        self::assertCount(3, $this->outboxRows()); // le 4e n'écrit rien
    }

    /**
     * @return list<array{type: string, friday_date: string, payload: string, published_at: ?string}>
     */
    private function outboxRows(): array
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        /** @var list<array{type: string, friday_date: string, payload: string, published_at: ?string}> $rows */
        $rows = $connection->fetchAllAssociative('SELECT type, friday_date, payload, published_at FROM outbox ORDER BY id ASC');

        return $rows;
    }

    private function handler(): ServeCoffeeHandler
    {
        // 2026-07-03 est un vendredi : la garde temporelle laisse passer.
        $friday = new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00'));

        return new ServeCoffeeHandler(
            new FridayCalendar($friday, self::TZ),
            new ResolveAnonymousVisitor(new DoctrineAnonymousVisitorRepository($this->registry), new UlidIdentifierGenerator(), $friday),
            new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $friday, 100),
            new ServeCoffee(
                new DoctrineTransactional($this->registry),
                new DoctrineFridayEditionRepository($this->registry),
                new DoctrineCoffeeContributionRepository($this->registry),
                new UlidIdentifierGenerator(),
                $friday,
                new OutboxDomainEventPublisher(new DoctrineOutboxEntryRepository($this->registry), $friday, new NullTracer()),
                new NullTracer(),
                new NullMetrics(),
            ),
        );
    }
}
