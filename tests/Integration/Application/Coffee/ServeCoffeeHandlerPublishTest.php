<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Coffee;

use App\Application\Coffee\CoffeeOutcomeStatus;
use App\Application\Coffee\ServeCoffee;
use App\Application\Coffee\ServeCoffeeHandler;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\RealTime\EnergyChanged;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Infrastructure\Persistence\DoctrineCoffeeContributionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Tests\Double\SpyDomainEventPublisher;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Phase 3 — la diffusion temps réel n'a lieu que POST-COMMIT et sur acceptation
 * RÉELLE (invariant A), avec une charge GLOBALE minimale (invariant B).
 */
#[CoversClass(ServeCoffeeHandler::class)]
final class ServeCoffeeHandlerPublishTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';
    private const string HASH = 'a-visitor-hash';

    private SpyDomainEventPublisher $publisher;

    public function testRealAcceptancePublishesGlobalEnergyOnce(): void
    {
        $handler = $this->handler();

        $outcome = $handler->handle(self::HASH, 'action-1');

        self::assertSame(CoffeeOutcomeStatus::Served, $outcome->status);
        self::assertCount(1, $this->publisher->calls);

        $call = $this->publisher->calls[0];
        self::assertSame('2026-07-03', $call['date']->format('Y-m-d'));
        $event = $call['event'];
        self::assertInstanceOf(EnergyChanged::class, $event);
        self::assertSame(1, $event->energy);
        self::assertSame(1, $event->energyVersion);
        self::assertSame('action-1', $event->actionId);
    }

    public function testIdempotentReplayDoesNotPublishAgain(): void
    {
        $handler = $this->handler();

        $handler->handle(self::HASH, 'same-key'); // acceptation → publie
        $handler->handle(self::HASH, 'same-key'); // rejeu → ne republie pas

        self::assertCount(1, $this->publisher->calls);
    }

    public function testQuotaRejectionDoesNotPublish(): void
    {
        $handler = $this->handler();

        $handler->handle(self::HASH, 'a1');
        $handler->handle(self::HASH, 'a2');
        $handler->handle(self::HASH, 'a3');
        $outcome = $handler->handle(self::HASH, 'a4'); // 4e → quota

        self::assertSame(CoffeeOutcomeStatus::LimitReached, $outcome->status);
        self::assertCount(3, $this->publisher->calls); // le 4e ne publie pas
    }

    private function handler(): ServeCoffeeHandler
    {
        // 2026-07-03 est un vendredi : la garde temporelle laisse passer.
        $friday = new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00'));
        $this->publisher = new SpyDomainEventPublisher();

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
            ),
            $this->publisher,
        );
    }
}
