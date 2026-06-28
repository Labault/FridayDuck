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
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Tests\Double\SpyDomainEventPublisher;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Invariant A : NOT_FRIDAY est décidé par l'horloge, pas par la base. Un mardi,
 * aucune édition n'est créée et aucun café n'est servi.
 */
#[CoversClass(ServeCoffeeHandler::class)]
final class ServeCoffeeNotFridayTest extends DatabaseTestCase
{
    public function testTuesdayIsRejectedWithoutAnyMutation(): void
    {
        // 2026-06-30 est un mardi (le vendredi de référence est le 2026-07-03).
        $tuesday = new FrozenClock(new \DateTimeImmutable('2026-06-30T10:00:00+02:00'));
        $publisher = new SpyDomainEventPublisher();

        $handler = new ServeCoffeeHandler(
            new FridayCalendar($tuesday, 'Europe/Paris'),
            new ResolveAnonymousVisitor(new DoctrineAnonymousVisitorRepository($this->registry), new UlidIdentifierGenerator(), $tuesday),
            new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $tuesday, 100),
            new ServeCoffee(
                new DoctrineTransactional($this->registry),
                new DoctrineFridayEditionRepository($this->registry),
                new DoctrineCoffeeContributionRepository($this->registry),
                new UlidIdentifierGenerator(),
                $tuesday,
            ),
            $publisher,
        );

        $outcome = $handler->handle(hash('sha256', 'A'), 'action-1');

        self::assertSame(CoffeeOutcomeStatus::NotFriday, $outcome->status);
        self::assertNull($outcome->result);
        self::assertSame(0, $this->countRows('coffee_contribution'));
        self::assertSame(0, $this->countRows('friday_edition'));
        // Invariant A : aucune publication hors vendredi.
        self::assertSame([], $publisher->calls);
    }
}
