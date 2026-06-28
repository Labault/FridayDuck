<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\RealTime;

use App\Application\RealTime\AccessoryResultsUpdated;
use App\Application\RealTime\OutboxRelay;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Persistence\DoctrineOutboxEntryRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Infrastructure\RealTime\OutboxDomainEventPublisher;
use App\Tests\Double\RecordingRelayMetrics;
use App\Tests\Double\RecordingTracer;
use App\Tests\Double\SpyRealTimeTransport;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Propagation bout-en-bout (§26.2, item 6) : le traceparent de la requête est
 * capturé sur la ligne outbox à l'écriture, puis RESTAURÉ par le relais → le span
 * de publication est rattaché à la trace d'origine par-dessus la frontière async.
 */
#[CoversClass(OutboxDomainEventPublisher::class)]
#[CoversClass(OutboxRelay::class)]
final class OutboxTracePropagationTest extends DatabaseTestCase
{
    private const string FRIDAY = '2026-07-03';
    private const string TRACEPARENT = '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01';

    public function testOutboxRowCarriesTheRequestTraceparent(): void
    {
        $tracer = new RecordingTracer();
        $tracer->traceparent = self::TRACEPARENT;

        $this->publisher($tracer)->publish(new \DateTimeImmutable(self::FRIDAY), new AccessoryResultsUpdated(1, []));

        self::assertSame(self::TRACEPARENT, $this->connection()->fetchOne('SELECT traceparent FROM outbox'));
    }

    public function testRelayPublishesAsChildOfTheOriginatingTrace(): void
    {
        // Écriture dans la trace d'origine (traceparent capturé sur la ligne).
        $writeTracer = new RecordingTracer();
        $writeTracer->traceparent = self::TRACEPARENT;
        $this->publisher($writeTracer)->publish(new \DateTimeImmutable(self::FRIDAY), new AccessoryResultsUpdated(1, []));

        // Relais (autre contexte) : le span de publish est ENFANT du traceparent stocké.
        $relayTracer = new RecordingTracer();
        $this->relay($relayTracer)->relayPending();

        $publishSpan = $relayTracer->span('mercure.update.publish');
        self::assertNotNull($publishSpan);
        self::assertSame(self::TRACEPARENT, $publishSpan['linkedTo']);
        self::assertSame('ACCESSORY_RESULTS_UPDATED', $publishSpan['attributes']['event.type']);
    }

    private function publisher(RecordingTracer $tracer): OutboxDomainEventPublisher
    {
        return new OutboxDomainEventPublisher(new DoctrineOutboxEntryRepository($this->registry), $this->clock(), $tracer);
    }

    private function relay(RecordingTracer $tracer): OutboxRelay
    {
        return new OutboxRelay(
            new DoctrineOutboxEntryRepository($this->registry),
            new SpyRealTimeTransport(),
            new DoctrineTransactional($this->registry),
            new RecordingRelayMetrics(),
            $this->clock(),
            $tracer,
        );
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00'));
    }

    private function connection(): Connection
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);

        return $connection;
    }
}
