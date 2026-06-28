<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\RealTime;

use App\Application\RealTime\AccessoryResultsUpdated;
use App\Application\RealTime\FridayTopic;
use App\Application\RealTime\OutboxRelay;
use App\Application\RealTime\OutboxRelayFailed;
use App\Domain\Shared\Clock\ClockInterface;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Persistence\DoctrineOutboxEntryRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Infrastructure\RealTime\OutboxDomainEventPublisher;
use App\Infrastructure\Telemetry\NullTracer;
use App\Tests\Double\RecordingRelayMetrics;
use App\Tests\Double\SpyRealTimeTransport;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Le relais (§20.6) publie les lignes non publiées EN ORDRE, race-safe, marque
 * `published_at`, ne republie jamais une ligne publiée (invariant B), rejoue
 * (at-least-once) les non marquées et bascule en échec si le hub est down
 * (invariant E). C'est le RELAIS qui pousse vers Mercure, pas le chemin requête.
 */
#[CoversClass(OutboxRelay::class)]
#[CoversClass(OutboxDomainEventPublisher::class)]
#[CoversClass(DoctrineOutboxEntryRepository::class)]
final class OutboxRelayTest extends DatabaseTestCase
{
    private const string FRIDAY = '2026-07-03';

    private SpyRealTimeTransport $transport;

    private RecordingRelayMetrics $metrics;

    public function testRelaysUnpublishedThenMarksThem(): void
    {
        $this->seed(new AccessoryResultsUpdated(1, []));

        $this->relay()->relayPending();

        self::assertCount(1, $this->transport->published);
        self::assertSame(FridayTopic::forDateString(self::FRIDAY), $this->transport->published[0]['topic']);
        self::assertSame(0, $this->countPending()); // marquée publiée
        self::assertSame(1, $this->metrics->succeeded);
    }

    public function testNeverRepublishesAnAlreadyPublishedRow(): void
    {
        $this->seed(new AccessoryResultsUpdated(1, []));
        $relay = $this->relay();

        $relay->relayPending();
        $relay->relayPending(); // second passage

        self::assertCount(1, $this->transport->published); // jamais republiée (invariant B)
    }

    public function testUnmarkedRowIsRepublishedAtLeastOnce(): void
    {
        $this->seed(new AccessoryResultsUpdated(1, []));
        $relay = $this->relay();

        $relay->relayPending(); // publiée + marquée

        // Simule un crash ENTRE publish et la persistance du marquage : le
        // `published_at` n'a pas survécu → la ligne redevient « à publier ».
        $this->resetPublishedFlags();

        $relay->relayPending();

        self::assertCount(2, $this->transport->published); // re-publication at-least-once
    }

    public function testRelaysEventsOfAnEditionInWriteOrder(): void
    {
        $this->seed(new AccessoryResultsUpdated(1, []));
        $this->seed(new AccessoryResultsUpdated(2, []));
        $this->seed(new AccessoryResultsUpdated(3, []));

        $this->relay()->relayPending();

        self::assertSame([1, 2, 3], array_map(
            fn (array $update): int => $this->sequenceOf($update['payload']),
            $this->transport->published,
        ));
    }

    public function testHubDownLeavesRowUnpublishedIncrementsAttemptsAndSignalsFailure(): void
    {
        $this->seed(new AccessoryResultsUpdated(1, []));
        $this->transport->down();

        try {
            $this->relay()->relayPending();
            self::fail('Le relais aurait dû signaler l’échec.');
        } catch (OutboxRelayFailed) {
            // attendu → rejeu Messenger, puis file d'échec après seuil (§25.4)
        }

        self::assertSame([], $this->transport->published);
        self::assertSame(1, $this->countPending()); // reste à publier
        self::assertSame(1, $this->attemptsFor(self::FRIDAY)); // tentative comptée
        self::assertSame(1, $this->metrics->failed);

        // Hub rétabli : le prochain passage publie (rien de perdu).
        $this->transport->down(false);
        $this->relay()->relayPending();
        self::assertCount(1, $this->transport->published);
        self::assertSame(0, $this->countPending());
    }

    public function testConcurrentRelayClaimDoesNotDoublePublish(): void
    {
        $this->seed(new AccessoryResultsUpdated(1, []));

        // Un relais concurrent (autre connexion) verrouille les lignes de l'édition.
        $other = DriverManager::getConnection($this->connection->getParams());
        $other->beginTransaction();
        $other->executeQuery(
            'SELECT id FROM outbox WHERE friday_date = :friday AND published_at IS NULL FOR UPDATE',
            ['friday' => self::FRIDAY],
        );

        // Notre relais SAUTE les lignes verrouillées (SKIP LOCKED) → rien publié.
        $this->relay()->relayPending();
        self::assertSame([], $this->transport->published);
        self::assertSame(1, $this->countPending());

        // L'autre relais libère : la ligne est publiée UNE seule fois.
        $other->rollBack();
        $other->close();

        $this->relay()->relayPending();
        self::assertCount(1, $this->transport->published);
        self::assertSame(0, $this->countPending());
    }

    private function relay(): OutboxRelay
    {
        return new OutboxRelay(
            new DoctrineOutboxEntryRepository($this->registry),
            $this->transport,
            new DoctrineTransactional($this->registry),
            $this->metrics,
            $this->clock(),
            new NullTracer(),
        );
    }

    private function seed(AccessoryResultsUpdated $event): void
    {
        if (!isset($this->transport)) {
            $this->transport = new SpyRealTimeTransport();
            $this->metrics = new RecordingRelayMetrics();
        }

        (new OutboxDomainEventPublisher(new DoctrineOutboxEntryRepository($this->registry), $this->clock(), new NullTracer()))
            ->publish(new \DateTimeImmutable(self::FRIDAY), $event);
    }

    private function clock(): ClockInterface
    {
        return new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00'));
    }

    private function sequenceOf(string $payload): int
    {
        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded) && \is_int($decoded['resultsSequence']));

        return $decoded['resultsSequence'];
    }

    private function countPending(): int
    {
        return (int) $this->conn()->fetchOne('SELECT COUNT(*) FROM outbox WHERE published_at IS NULL');
    }

    private function attemptsFor(string $fridayDate): int
    {
        return (int) $this->conn()->fetchOne('SELECT MAX(attempts) FROM outbox WHERE friday_date = :friday', ['friday' => $fridayDate]);
    }

    private function resetPublishedFlags(): void
    {
        $this->conn()->executeStatement('UPDATE outbox SET published_at = NULL');
    }

    private function conn(): Connection
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        return $connection;
    }
}
