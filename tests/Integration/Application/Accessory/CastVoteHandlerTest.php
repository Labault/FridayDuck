<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Accessory;

use App\Application\Accessory\AccessoryOptionsReader;
use App\Application\Accessory\AccessoryWinnerViewBuilder;
use App\Application\Accessory\CastVote;
use App\Application\Accessory\CastVoteHandler;
use App\Application\Accessory\ResolveAccessoryOptions;
use App\Application\Accessory\ResolveAccessoryWinner;
use App\Application\Accessory\VoteOutcomeStatus;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\RealTime\AccessoryResultsUpdated;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Accessory\AccessoryVoteSchedule;
use App\Domain\Friday\FridayCalendar;
use App\Domain\Friday\FridayEdition;
use App\Domain\Shared\Clock\ClockInterface;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAccessoryRepository;
use App\Infrastructure\Persistence\DoctrineAccessoryVoteRepository;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Infrastructure\Persistence\DoctrineFridayAccessoryOptionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Infrastructure\Telemetry\NullMetrics;
use App\Infrastructure\Telemetry\NullTracer;
use App\Tests\Double\SpyDomainEventPublisher;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CastVoteHandler::class)]
#[CoversClass(CastVote::class)]
final class CastVoteHandlerTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';
    private const string BEFORE = '2026-07-03T10:00:00+02:00';
    private const string AFTER = '2026-07-03T15:00:00+02:00';
    private const string TUESDAY = '2026-06-30T10:00:00+02:00';

    public function testTuesdayIsNotFriday(): void
    {
        $publisher = new SpyDomainEventPublisher();
        $outcome = $this->handler(self::TUESDAY, $publisher)->handle('visitor-A', 'cto_glasses');

        self::assertSame(VoteOutcomeStatus::NotFriday, $outcome->status);
        self::assertSame([], $publisher->calls);
        self::assertSame(0, $this->countRows('friday_edition'));
    }

    public function testAcceptedVoteCountsAndPublishesResults(): void
    {
        $code = $this->optionCodes(self::BEFORE)[0];
        $publisher = new SpyDomainEventPublisher();

        $outcome = $this->handler(self::BEFORE, $publisher)->handle('visitor-A', $code);

        self::assertSame(VoteOutcomeStatus::Accepted, $outcome->status);
        self::assertNotNull($outcome->accepted);
        self::assertSame($code, $outcome->accepted->accessoryCode);
        self::assertSame(1, $outcome->accepted->resultsSequence);
        self::assertSame(1, $this->voteCount($code));
        self::assertSame(1, $this->countRows('accessory_vote'));

        $events = $publisher->eventsOfType('ACCESSORY_RESULTS_UPDATED');
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(AccessoryResultsUpdated::class, $event);
        self::assertSame(1, $event->resultsSequence);
    }

    public function testSecondVoteBySameVisitorIsAlreadyVotedAndDoesNotReincrement(): void
    {
        $code = $this->optionCodes(self::BEFORE)[0];
        $publisher = new SpyDomainEventPublisher();
        $handler = $this->handler(self::BEFORE, $publisher);

        $handler->handle('visitor-A', $code);
        $second = $handler->handle('visitor-A', $code);

        self::assertSame(VoteOutcomeStatus::AlreadyVoted, $second->status);
        self::assertSame(1, $this->voteCount($code)); // pas de ré-incrément
        self::assertSame(1, $this->countRows('accessory_vote'));
        // Une seule publication de résultats (le rejeu ne republie pas).
        self::assertCount(1, $publisher->eventsOfType('ACCESSORY_RESULTS_UPDATED'));
    }

    public function testTwoVisitorsVotingSameOptionYieldCountTwo(): void
    {
        // Course du compteur (§D) : le verrou d'édition (prouvé par
        // CoffeePessimisticLockTest, même mécanisme) sérialise les deux votes —
        // aucun n'est perdu.
        $code = $this->optionCodes(self::BEFORE)[0];
        $handler = $this->handler(self::BEFORE, new SpyDomainEventPublisher());

        self::assertSame(VoteOutcomeStatus::Accepted, $handler->handle('visitor-A', $code)->status);
        self::assertSame(VoteOutcomeStatus::Accepted, $handler->handle('visitor-B', $code)->status);

        self::assertSame(2, $this->voteCount($code));
        self::assertSame(2, $this->countRows('accessory_vote'));
    }

    public function testInvalidAccessoryIsRejectedWithoutPublishing(): void
    {
        $publisher = new SpyDomainEventPublisher();

        $outcome = $this->handler(self::BEFORE, $publisher)->handle('visitor-A', 'definitely_not_an_accessory');

        self::assertSame(VoteOutcomeStatus::InvalidAccessory, $outcome->status);
        self::assertSame(0, $this->countRows('accessory_vote'));
        self::assertSame([], $publisher->eventsOfType('ACCESSORY_RESULTS_UPDATED'));
    }

    public function testVoteClosedReturnsWinnerButResolvesSilently(): void
    {
        // Phase 6a (invariant E) : la résolution paresseuse du gagnant est
        // SILENCIEUSE — l'annonce ACCESSORY_WINNER_SELECTED revient au Scheduler.
        $this->optionCodes(self::BEFORE); // garantit l'édition + les options
        $publisher = new SpyDomainEventPublisher();
        $handler = $this->handler(self::AFTER, $publisher);

        $first = $handler->handle('visitor-A', 'cto_glasses');
        $second = $handler->handle('visitor-B', 'cto_glasses');

        self::assertSame(VoteOutcomeStatus::VoteClosed, $first->status);
        self::assertNotNull($first->winner);
        self::assertNotNull($second->winner);
        self::assertSame($first->winner->code, $second->winner->code); // gagnant immuable
        self::assertSame($first->winner->code, $this->winner());
        // Sérialisation enrichie : de quoi monter/étiqueter (§10.5, §28.3).
        self::assertNotSame('', $first->winner->label);
        self::assertContains($first->winner->slot, ['head', 'body', 'hand']);
        self::assertStringStartsWith('accessory-', $first->winner->svgGroupId);

        // AUCUNE annonce de gagnant émise par le chemin paresseux (invariant E).
        self::assertSame([], $publisher->eventsOfType('ACCESSORY_WINNER_SELECTED'));
    }

    public function testOptionsAreExactlyThreeAndDeterministicAcrossRequests(): void
    {
        $first = $this->optionCodes(self::BEFORE);
        $again = $this->optionCodes(self::BEFORE);

        self::assertCount(3, $first);
        self::assertSame($first, $again); // stables entre requêtes
        self::assertSame(3, $this->countRows('friday_accessory_option'));
    }

    /**
     * @return list<string> codes des options du jour, dans l'ordre d'affichage
     */
    private function optionCodes(string $now): array
    {
        $edition = $this->edition($now);
        $this->resolveOptions()->resolve($edition->id(), $edition->fridayDate());

        return array_map(
            static fn (\App\Application\Accessory\AccessoryOptionView $view): string => $view->code,
            $this->optionsReader()->forEdition($edition->id()),
        );
    }

    private function edition(string $now): FridayEdition
    {
        $state = (new FridayCalendar(new FrozenClock(new \DateTimeImmutable($now)), self::TZ))->currentState();

        return $this->resolveEdition(new FrozenClock(new \DateTimeImmutable($now)))->resolve($state->fridayDate, $state->timezoneName());
    }

    private function voteCount(string $code): int
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        return (int) $connection->fetchOne(
            'SELECT o.vote_count FROM friday_accessory_option o JOIN accessory a ON a.id = o.accessory_id WHERE a.code = :code',
            ['code' => $code],
        );
    }

    private function winner(): ?string
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);
        $value = $connection->fetchOne('SELECT winner_accessory_code FROM friday_edition');

        return \is_string($value) ? $value : null;
    }

    private function handler(string $now, SpyDomainEventPublisher $publisher): CastVoteHandler
    {
        $clock = new FrozenClock(new \DateTimeImmutable($now));

        return new CastVoteHandler(
            new FridayCalendar($clock, self::TZ),
            new AccessoryVoteSchedule($clock, self::TZ),
            new ResolveAnonymousVisitor(new DoctrineAnonymousVisitorRepository($this->registry), new UlidIdentifierGenerator(), $clock),
            $this->resolveEdition($clock),
            $this->resolveOptions(),
            $this->resolveWinner(),
            new DoctrineAccessoryRepository($this->registry),
            new AccessoryWinnerViewBuilder(new DoctrineAccessoryRepository($this->registry)),
            new CastVote(
                new DoctrineTransactional($this->registry),
                new DoctrineFridayEditionRepository($this->registry),
                new DoctrineFridayAccessoryOptionRepository($this->registry),
                new DoctrineAccessoryVoteRepository($this->registry),
                new UlidIdentifierGenerator(),
                $clock,
                $publisher,
                $this->optionsReader(),
                new NullTracer(),
                new NullMetrics(),
            ),
        );
    }

    private function resolveEdition(ClockInterface $clock): ResolveCurrentFridayEdition
    {
        return new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $clock, 100);
    }

    private function resolveOptions(): ResolveAccessoryOptions
    {
        return new ResolveAccessoryOptions(new DoctrineAccessoryRepository($this->registry), new DoctrineFridayAccessoryOptionRepository($this->registry), new UlidIdentifierGenerator());
    }

    private function resolveWinner(): ResolveAccessoryWinner
    {
        return new ResolveAccessoryWinner(new DoctrineTransactional($this->registry), new DoctrineFridayEditionRepository($this->registry), $this->optionsReader());
    }

    private function optionsReader(): AccessoryOptionsReader
    {
        return new AccessoryOptionsReader(new DoctrineAccessoryRepository($this->registry), new DoctrineFridayAccessoryOptionRepository($this->registry));
    }
}
