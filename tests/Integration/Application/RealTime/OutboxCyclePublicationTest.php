<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\RealTime;

use App\Application\Accessory\ResolveAccessoryOptions;
use App\Application\Advice\ResolveAdvice;
use App\Application\Cycle\OpenFriday;
use App\Application\Cycle\PrepareFridayEdition;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\RealTime\OutboxRelay;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAccessoryRepository;
use App\Infrastructure\Persistence\DoctrineAdviceRepository;
use App\Infrastructure\Persistence\DoctrineFridayAccessoryOptionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineOutboxEntryRepository;
use App\Infrastructure\Persistence\DoctrineProcessedMessageGuard;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Infrastructure\RealTime\OutboxDomainEventPublisher;
use App\Infrastructure\Telemetry\NullTracer;
use App\Tests\Double\RecordingRelayMetrics;
use App\Tests\Double\SpyRealTimeTransport;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Invariant D — les ANNONCES de cycle (Phase 6a) deviennent durables via l'outbox
 * SANS modifier 6a : on ne change que l'implémentation derrière le port. OpenFriday,
 * inchangé, écrit désormais FRIDAY_OPENED dans l'outbox ; le relais le publie.
 */
#[CoversClass(OpenFriday::class)]
#[CoversClass(OutboxDomainEventPublisher::class)]
final class OutboxCyclePublicationTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';
    private const string FRIDAY = '2026-07-03';

    public function testCycleAnnouncementGoesThroughOutboxThenRelay(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-07-03T00:00:00+02:00'));
        $friday = new \DateTimeImmutable(self::FRIDAY);

        $this->openFriday($clock)->open($friday, self::TZ);

        // L'annonce a été ÉCRITE dans l'outbox (durable), pas poussée en synchrone.
        self::assertSame('FRIDAY_OPENED', $this->conn()->fetchOne('SELECT type FROM outbox WHERE published_at IS NULL'));

        // Le relais la publie une fois.
        $transport = new SpyRealTimeTransport();
        (new OutboxRelay(
            new DoctrineOutboxEntryRepository($this->registry),
            $transport,
            new DoctrineTransactional($this->registry),
            new RecordingRelayMetrics(),
            $clock,
            new NullTracer(),
        ))->relayPending();

        self::assertCount(1, $transport->published);
        $payload = json_decode($transport->published[0]['payload'], true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload));
        self::assertSame('FRIDAY_OPENED', $payload['type']);
    }

    private function openFriday(FrozenClock $clock): OpenFriday
    {
        $editionRepo = new DoctrineFridayEditionRepository($this->registry);
        $idGen = new UlidIdentifierGenerator();
        $transactional = new DoctrineTransactional($this->registry);

        $prepare = new PrepareFridayEdition(
            new ResolveCurrentFridayEdition($editionRepo, $idGen, $clock, 100),
            new ResolveAccessoryOptions(new DoctrineAccessoryRepository($this->registry), new DoctrineFridayAccessoryOptionRepository($this->registry), $idGen),
            new ResolveAdvice($transactional, $editionRepo, new DoctrineAdviceRepository($this->registry)),
        );

        return new OpenFriday(
            $prepare,
            new DoctrineProcessedMessageGuard($this->registry, $clock),
            new OutboxDomainEventPublisher(new DoctrineOutboxEntryRepository($this->registry), $clock, new NullTracer()),
            $transactional,
        );
    }

    private function conn(): Connection
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        return $connection;
    }
}
