<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Advice;

use App\Application\Advice\AdviceReactionOutcomeStatus;
use App\Application\Advice\ReactToAdvice;
use App\Application\Advice\ReactToAdviceHandler;
use App\Application\Advice\ResolveAdvice;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\RealTime\AdviceReactionChanged;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Advice\Advice;
use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAdviceReactionRepository;
use App\Infrastructure\Persistence\DoctrineAdviceRepository;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Tests\Double\SpyDomainEventPublisher;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReactToAdviceHandler::class)]
#[CoversClass(ReactToAdvice::class)]
#[CoversClass(ResolveAdvice::class)]
final class ReactToAdviceHandlerTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';
    private const string FRIDAY_MORNING = '2026-07-03T10:00:00+02:00';
    private const string FRIDAY_AFTERNOON = '2026-07-03T18:00:00+02:00';
    private const string TUESDAY = '2026-06-30T10:00:00+02:00';

    public function testTuesdayIsNotFriday(): void
    {
        $publisher = new SpyDomainEventPublisher();
        $outcome = $this->handler(self::TUESDAY, $publisher)->handle('visitor-A', 'CONCERNING');

        self::assertSame(AdviceReactionOutcomeStatus::NotFriday, $outcome->status);
        self::assertSame([], $publisher->calls);
    }

    public function testInvalidReactionIsRejected(): void
    {
        $outcome = $this->handler(self::FRIDAY_MORNING, new SpyDomainEventPublisher())->handle('visitor-A', 'WAT');

        self::assertSame(AdviceReactionOutcomeStatus::InvalidReaction, $outcome->status);
    }

    public function testReactionsAreOpenAllFridayUnlikeTheVote(): void
    {
        // 18:00 (après la clôture du vote à 14:00) : réagir reste possible (inv. A).
        $outcome = $this->handler(self::FRIDAY_AFTERNOON, new SpyDomainEventPublisher())->handle('visitor-A', 'TAKING_NOTES');

        self::assertSame(AdviceReactionOutcomeStatus::Recorded, $outcome->status);
        self::assertNotNull($outcome->result);
        self::assertTrue($outcome->result->changed);
        self::assertSame(1, $this->counter('taking_notes_count'));
    }

    public function testFirstReactionInsertsIncrementsAndPublishes(): void
    {
        $publisher = new SpyDomainEventPublisher();
        $outcome = $this->handler(self::FRIDAY_MORNING, $publisher)->handle('visitor-A', 'CONCERNING');

        self::assertSame(AdviceReactionOutcomeStatus::Recorded, $outcome->status);
        self::assertNotNull($outcome->result);
        self::assertTrue($outcome->result->changed);
        self::assertSame(1, $this->counter('concerning_count'));
        self::assertSame(1, $this->countRows('advice_reaction'));

        $events = $publisher->eventsOfType('ADVICE_REACTION_CHANGED');
        self::assertCount(1, $events);
        self::assertInstanceOf(AdviceReactionChanged::class, $events[0]);
        self::assertSame(1, $events[0]->concerning);
    }

    public function testSameReactionIsNoOpAndDoesNotPublish(): void
    {
        $publisher = new SpyDomainEventPublisher();
        $handler = $this->handler(self::FRIDAY_MORNING, $publisher);

        $handler->handle('visitor-A', 'CONCERNING');
        $second = $handler->handle('visitor-A', 'CONCERNING'); // même réaction → no-op

        self::assertNotNull($second->result);
        self::assertFalse($second->result->changed);
        self::assertSame(1, $this->counter('concerning_count')); // inchangé
        self::assertSame(1, $this->countRows('advice_reaction'));
        // Une seule publication (l'insert), PAS de seconde (le no-op).
        self::assertCount(1, $publisher->eventsOfType('ADVICE_REACTION_CHANGED'));
    }

    public function testChangingReactionSwapsCountersAtomically(): void
    {
        $publisher = new SpyDomainEventPublisher();
        $handler = $this->handler(self::FRIDAY_MORNING, $publisher);

        $handler->handle('visitor-A', 'CONCERNING');
        $handler->handle('visitor-A', 'ALREADY_DONE'); // change → décrément ancien + incrément nouveau

        self::assertSame(0, $this->counter('concerning_count'));
        self::assertSame(1, $this->counter('already_done_count'));
        self::assertSame(1, $this->countRows('advice_reaction')); // toujours une seule ligne (mutable)
        self::assertCount(2, $publisher->eventsOfType('ADVICE_REACTION_CHANGED'));
    }

    public function testTwoVisitorsAndTotalsStayCoherent(): void
    {
        $handler = $this->handler(self::FRIDAY_MORNING, new SpyDomainEventPublisher());

        $handler->handle('visitor-A', 'CONCERNING');
        $handler->handle('visitor-B', 'CONCERNING');
        $handler->handle('visitor-A', 'TAKING_NOTES'); // A change d'avis

        self::assertSame(1, $this->counter('concerning_count')); // B seul
        self::assertSame(1, $this->counter('taking_notes_count')); // A
        self::assertSame(0, $this->counter('already_done_count'));
        self::assertSame(2, $this->countRows('advice_reaction'));
    }

    public function testAdviceIsDeterministicByDateAndImmutable(): void
    {
        $resolveAdvice = $this->resolveAdvice($this->clock(self::FRIDAY_MORNING));
        $friday = new \DateTimeImmutable('2026-07-03');

        $first = $resolveAdvice->resolve($friday, self::TZ);
        $again = $resolveAdvice->resolve($friday, self::TZ);

        self::assertInstanceOf(Advice::class, $first);
        self::assertSame($first->slug(), $again->slug()); // déterministe + immuable
        self::assertSame($first->slug(), $this->persistedAdviceSlug());
    }

    private function counter(string $column): int
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        return (int) $connection->fetchOne('SELECT '.$column.' FROM friday_edition');
    }

    private function persistedAdviceSlug(): ?string
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);
        $value = $connection->fetchOne('SELECT a.slug FROM friday_edition e JOIN advice a ON a.id = e.advice_id');

        return \is_string($value) ? $value : null;
    }

    private function handler(string $now, SpyDomainEventPublisher $publisher): ReactToAdviceHandler
    {
        $clock = $this->clock($now);

        return new ReactToAdviceHandler(
            new FridayCalendar($clock, self::TZ),
            new ResolveAnonymousVisitor(new DoctrineAnonymousVisitorRepository($this->registry), new UlidIdentifierGenerator(), $clock),
            new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $clock, 100),
            $this->resolveAdvice($clock),
            new ReactToAdvice(
                new DoctrineTransactional($this->registry),
                new DoctrineFridayEditionRepository($this->registry),
                new DoctrineAdviceReactionRepository($this->registry),
                new UlidIdentifierGenerator(),
                $clock,
            ),
            $publisher,
        );
    }

    private function resolveAdvice(FrozenClock $clock): ResolveAdvice
    {
        // L'édition doit exister pour figer le conseil : on la garantit via le resolver.
        (new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $clock, 100))
            ->resolve(new \DateTimeImmutable('2026-07-03'), self::TZ);

        return new ResolveAdvice(
            new DoctrineTransactional($this->registry),
            new DoctrineFridayEditionRepository($this->registry),
            new DoctrineAdviceRepository($this->registry),
        );
    }

    private function clock(string $now): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable($now));
    }
}
