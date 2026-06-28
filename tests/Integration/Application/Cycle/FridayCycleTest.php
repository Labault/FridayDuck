<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Cycle;

use App\Application\Accessory\AccessoryOptionsReader;
use App\Application\Accessory\AccessoryWinnerViewBuilder;
use App\Application\Accessory\ResolveAccessoryOptions;
use App\Application\Accessory\ResolveAccessoryWinner;
use App\Application\Advice\ResolveAdvice;
use App\Application\Cycle\CloseFriday;
use App\Application\Cycle\CloseVote;
use App\Application\Cycle\CycleStep;
use App\Application\Cycle\FridayCycle;
use App\Application\Cycle\GenerateWeeklyReport;
use App\Application\Cycle\OpenFriday;
use App\Application\Cycle\PrepareFridayEdition;
use App\Application\Cycle\PrepareReport;
use App\Application\Cycle\PublishWinner;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Domain\Accessory\AccessoryVoteSchedule;
use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAccessoryRepository;
use App\Infrastructure\Persistence\DoctrineAdviceRepository;
use App\Infrastructure\Persistence\DoctrineFridayAccessoryOptionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineFridayVisitRepository;
use App\Infrastructure\Persistence\DoctrineProcessedMessageGuard;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Infrastructure\Persistence\DoctrineWeeklyReportRepository;
use App\Presentation\Console\FridayRepairCommand;
use App\Tests\Double\SpyDomainEventPublisher;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(FridayCycle::class)]
#[CoversClass(OpenFriday::class)]
#[CoversClass(PublishWinner::class)]
#[CoversClass(CloseFriday::class)]
#[CoversClass(GenerateWeeklyReport::class)]
final class FridayCycleTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';
    private const string FRIDAY = '2026-07-03';
    private const string NOW = '2026-07-03T14:30:00+02:00';

    private SpyDomainEventPublisher $publisher;

    public function testPrepareCreatesEditionOptionsAndAdvice(): void
    {
        $this->cycle()->run(CycleStep::PrepareEdition, $this->friday(), self::TZ);

        self::assertSame(1, $this->countRows('friday_edition'));
        self::assertSame(3, $this->countRows('friday_accessory_option'));
        self::assertNotNull($this->scalar('SELECT advice_id FROM friday_edition'));
    }

    public function testPrepareIsIdempotentAndConvergesWithLazyResolution(): void
    {
        $cycle = $this->cycle();
        $cycle->run(CycleStep::PrepareEdition, $this->friday(), self::TZ);
        $cycle->run(CycleStep::PrepareEdition, $this->friday(), self::TZ); // rejeu → no-op

        // Résolution PARESSEUSE après la tâche → même état (convergence §25.2).
        $this->lazyResolveEdition();

        self::assertSame(1, $this->countRows('friday_edition'));
        self::assertSame(3, $this->countRows('friday_accessory_option'));
    }

    public function testOpenFridayAnnouncesExactlyOnce(): void
    {
        $cycle = $this->cycle();
        $cycle->run(CycleStep::PublishFridayOpened, $this->friday(), self::TZ);
        $cycle->run(CycleStep::PublishFridayOpened, $this->friday(), self::TZ); // rejeu

        self::assertCount(1, $this->publisher->eventsOfType('FRIDAY_OPENED'));
    }

    public function testPublishWinnerResolvesAndAnnouncesExactlyOnce(): void
    {
        $cycle = $this->cycle();
        $cycle->run(CycleStep::CloseVote, $this->friday(), self::TZ); // résout le gagnant (silencieux)
        self::assertSame([], $this->publisher->eventsOfType('ACCESSORY_WINNER_SELECTED')); // CloseVote n'annonce pas
        self::assertNotNull($this->scalar('SELECT winner_accessory_code FROM friday_edition'));

        $cycle->run(CycleStep::PublishWinner, $this->friday(), self::TZ);
        $cycle->run(CycleStep::PublishWinner, $this->friday(), self::TZ); // rejeu

        self::assertCount(1, $this->publisher->eventsOfType('ACCESSORY_WINNER_SELECTED'));
    }

    public function testCloseFridayClosesStatusAndAnnouncesOnce(): void
    {
        $cycle = $this->cycle();
        $cycle->run(CycleStep::CloseFriday, $this->friday(), self::TZ);
        $cycle->run(CycleStep::CloseFriday, $this->friday(), self::TZ); // rejeu

        self::assertSame('CLOSED', $this->scalar('SELECT status FROM friday_edition'));
        self::assertCount(1, $this->publisher->eventsOfType('FRIDAY_CLOSED'));
    }

    public function testGenerateReportAggregatesOnce(): void
    {
        $cycle = $this->cycle();
        $cycle->run(CycleStep::CloseVote, $this->friday(), self::TZ); // fige le gagnant
        $cycle->run(CycleStep::GenerateReport, $this->friday(), self::TZ);
        $cycle->run(CycleStep::GenerateReport, $this->friday(), self::TZ); // rejeu

        self::assertSame(1, $this->countRows('weekly_report'));
        self::assertSame('2026-W27', $this->scalar('SELECT iso_week FROM weekly_report'));
        self::assertNotNull($this->scalar('SELECT winner_accessory_code FROM weekly_report'));
        self::assertNotNull($this->scalar('SELECT advice_slug FROM weekly_report'));
    }

    private function lazyResolveEdition(): void
    {
        (new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $this->clock(), 100))
            ->resolve($this->friday(), self::TZ);
    }

    private function friday(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::FRIDAY);
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable(self::NOW));
    }

    private function scalar(string $sql): ?string
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);
        $value = $connection->fetchOne($sql);

        return false === $value ? null : (\is_string($value) ? $value : (string) $value);
    }

    public function testOpenStatusOnSaturdayStaysDormantAndVoteClosed(): void
    {
        // Édition créée (statut OPEN par défaut)…
        $this->cycle()->run(CycleStep::PrepareEdition, $this->friday(), self::TZ);
        self::assertSame('OPEN', $this->scalar('SELECT status FROM friday_edition'));

        // …mais un samedi, l'HORLOGE décide : DORMANT + vote fermé (invariant B).
        $saturday = new FrozenClock(new \DateTimeImmutable('2026-07-04T10:00:00+02:00'));
        self::assertFalse((new FridayCalendar($saturday, self::TZ))->currentState()->active);
        self::assertFalse((new AccessoryVoteSchedule($saturday, self::TZ))->isOpen($this->friday()));
    }

    public function testRepairBringsNeverPreparedEditionToCorrectStateOnce(): void
    {
        // Samedi : rattrapage d'un vendredi JAMAIS préparé → tout le cycle s'applique.
        $saturday = new FrozenClock(new \DateTimeImmutable('2026-07-04T01:00:00+02:00'));
        $publisher = new SpyDomainEventPublisher();
        $command = new FridayRepairCommand($this->cycleWith($saturday, $publisher), $saturday, self::TZ);

        $tester = new CommandTester($command);
        $tester->execute(['date' => self::FRIDAY]);
        $tester->assertCommandIsSuccessful();

        self::assertSame(1, $this->countRows('friday_edition'));
        self::assertSame('CLOSED', $this->scalar('SELECT status FROM friday_edition'));
        self::assertSame(1, $this->countRows('weekly_report'));
        // Annonces manquantes émises UNE fois chacune.
        self::assertCount(1, $publisher->eventsOfType('FRIDAY_OPENED'));
        self::assertCount(1, $publisher->eventsOfType('ACCESSORY_WINNER_SELECTED'));
        self::assertCount(1, $publisher->eventsOfType('FRIDAY_CLOSED'));
    }

    public function testConvergesWhenLazyResolutionPrecedesTheTask(): void
    {
        // Sens INVERSE de testPrepareIsIdempotentAndConvergesWithLazyResolution :
        // la requête paresseuse crée l'édition AVANT que la tâche ne tourne.
        $this->lazyResolveEdition();

        $cycle = $this->cycle();
        $cycle->run(CycleStep::PrepareEdition, $this->friday(), self::TZ); // → no-op

        self::assertSame(1, $this->countRows('friday_edition'));
        self::assertSame(3, $this->countRows('friday_accessory_option'));
        // La préparation est SILENCIEUSE : aucune annonce (invariant E).
        self::assertSame([], $this->publisher->calls);
    }

    public function testFridayOpenedAnnouncedOnceEvenWhenLazyResolutionRanFirst(): void
    {
        // La résolution paresseuse répare l'édition en SILENCE (invariant E) ; c'est
        // la tâche (ou le rattrapage) qui annonce — exactement une fois, même si
        // l'édition existait déjà avant qu'elle ne tourne.
        $this->lazyResolveEdition();

        $cycle = $this->cycle();
        $cycle->run(CycleStep::PublishFridayOpened, $this->friday(), self::TZ);
        $cycle->run(CycleStep::PublishFridayOpened, $this->friday(), self::TZ);

        self::assertCount(1, $this->publisher->eventsOfType('FRIDAY_OPENED'));
    }

    public function testSchedulerThenRepairAnnounceEachCycleEventExactlyOnce(): void
    {
        // Annonces UNE fois même avec Scheduler PUIS rattrapage : le garde dédup
        // les clés à travers les DEUX chemins (table partagée, invariant C/E).
        $saturday = new FrozenClock(new \DateTimeImmutable('2026-07-04T01:00:00+02:00'));

        // 1) Le Scheduler ouvre le vendredi (annonce via le publisher du Scheduler).
        $schedulerPublisher = new SpyDomainEventPublisher();
        $this->cycleWith($saturday, $schedulerPublisher)
            ->run(CycleStep::PublishFridayOpened, $this->friday(), self::TZ);
        self::assertCount(1, $schedulerPublisher->eventsOfType('FRIDAY_OPENED'));

        // 2) Le rattrapage repasse derrière (publisher distinct) : il complète le
        //    cycle mais NE rejoue PAS l'ouverture déjà annoncée.
        $repairPublisher = new SpyDomainEventPublisher();
        $command = new FridayRepairCommand($this->cycleWith($saturday, $repairPublisher), $saturday, self::TZ);
        (new CommandTester($command))->execute(['date' => self::FRIDAY]);

        self::assertCount(0, $repairPublisher->eventsOfType('FRIDAY_OPENED')); // déjà annoncé
        self::assertCount(1, $repairPublisher->eventsOfType('ACCESSORY_WINNER_SELECTED'));
        self::assertCount(1, $repairPublisher->eventsOfType('FRIDAY_CLOSED'));
        self::assertSame('CLOSED', $this->scalar('SELECT status FROM friday_edition'));
        self::assertSame(1, $this->countRows('weekly_report'));
    }

    private function cycle(): FridayCycle
    {
        $this->publisher = new SpyDomainEventPublisher();

        return $this->cycleWith($this->clock(), $this->publisher);
    }

    private function cycleWith(FrozenClock $clock, SpyDomainEventPublisher $publisher): FridayCycle
    {
        $editionRepo = new DoctrineFridayEditionRepository($this->registry);
        $optionRepo = new DoctrineFridayAccessoryOptionRepository($this->registry);
        $accessoryRepo = new DoctrineAccessoryRepository($this->registry);
        $adviceRepo = new DoctrineAdviceRepository($this->registry);
        $transactional = new DoctrineTransactional($this->registry);
        $idGen = new UlidIdentifierGenerator();
        $guard = new DoctrineProcessedMessageGuard($this->registry, $clock);
        $optionsReader = new AccessoryOptionsReader($accessoryRepo, $optionRepo);
        $winnerViewBuilder = new AccessoryWinnerViewBuilder($accessoryRepo);

        $prepare = new PrepareFridayEdition(
            new ResolveCurrentFridayEdition($editionRepo, $idGen, $clock, 100),
            new ResolveAccessoryOptions($accessoryRepo, $optionRepo, $idGen),
            new ResolveAdvice($transactional, $editionRepo, $adviceRepo),
        );
        $resolveWinner = new ResolveAccessoryWinner($transactional, $editionRepo, $optionsReader);

        return new FridayCycle(
            $prepare,
            new OpenFriday($prepare, $guard, $publisher, $transactional),
            new CloseVote($prepare, $resolveWinner, $guard),
            new PublishWinner($prepare, $resolveWinner, $winnerViewBuilder, $guard, $publisher, $transactional),
            new PrepareReport($prepare, $resolveWinner),
            new CloseFriday($prepare, $transactional, $editionRepo, $guard, $publisher, $clock),
            new GenerateWeeklyReport($editionRepo, new DoctrineFridayVisitRepository($this->registry), $adviceRepo, new DoctrineWeeklyReportRepository($this->registry), $idGen, $clock),
        );
    }
}
