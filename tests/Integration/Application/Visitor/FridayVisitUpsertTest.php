<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Visitor;

use App\Application\Visitor\RecordFridayVisit;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineFridayVisitRepository;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RecordFridayVisit::class)]
#[CoversClass(DoctrineFridayVisitRepository::class)]
final class FridayVisitUpsertTest extends DatabaseTestCase
{
    public function testUpsertKeepsSingleRowAndUpdatesLastSeen(): void
    {
        $editionId = str_pad('EDITION', 26, '0');
        $visitorId = str_pad('VISITOR', 26, '0');

        $this->recorderAt('2026-07-03T10:00:00+02:00')->record($editionId, $visitorId);
        self::assertSame(1, $this->countRows('friday_visit'));

        $this->recorderAt('2026-07-03T11:30:00+02:00')->record($editionId, $visitorId);
        self::assertSame(1, $this->countRows('friday_visit'));

        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);
        $row = $connection->fetchAssociative('SELECT first_seen_at, last_seen_at FROM friday_visit');
        self::assertIsArray($row);

        // first_seen_at figé au premier passage, last_seen_at mis à jour.
        self::assertSame('2026-07-03 10:00:00', substr((string) $row['first_seen_at'], 0, 19));
        self::assertSame('2026-07-03 11:30:00', substr((string) $row['last_seen_at'], 0, 19));
    }

    private function recorderAt(string $instant): RecordFridayVisit
    {
        return new RecordFridayVisit(
            new DoctrineFridayVisitRepository($this->registry),
            new UlidIdentifierGenerator(),
            new FrozenClock(new \DateTimeImmutable($instant)),
        );
    }
}
