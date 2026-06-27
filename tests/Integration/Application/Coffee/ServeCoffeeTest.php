<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Coffee;

use App\Application\Coffee\ServeCoffee;
use App\Domain\Coffee\CoffeeLimitReached;
use App\Domain\Friday\FridayEdition;
use App\Domain\Visitor\AnonymousVisitor;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Infrastructure\Persistence\DoctrineCoffeeContributionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ServeCoffee::class)]
#[CoversClass(DoctrineTransactional::class)]
final class ServeCoffeeTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';

    private \DateTimeImmutable $friday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->friday = new \DateTimeImmutable('2026-07-03');
    }

    public function testServeRecordsContributionAndRecalculatesEnergy(): void
    {
        $this->createEdition();
        $visitor = $this->createVisitor('A');

        $result = $this->serveCoffee()->serve($visitor, $this->friday, self::TZ, 'action-1');

        self::assertFalse($result->replayed);
        self::assertSame(0, $result->previousEnergy);
        self::assertSame(1, $result->currentEnergy);
        self::assertSame(2, $result->remainingCoffees);
        self::assertSame(1, $this->countRows('coffee_contribution'));
        $this->assertEditionState(energy: 1, coffeeCount: 1, version: 1);
    }

    public function testIdempotentReplayAppliesEnergyExactlyOnce(): void
    {
        $this->createEdition();
        $visitor = $this->createVisitor('A');

        $first = $this->serveCoffee()->serve($visitor, $this->friday, self::TZ, 'same-key');
        $second = $this->serveCoffee()->serve($visitor, $this->friday, self::TZ, 'same-key');

        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame($first->contributionId, $second->contributionId);
        self::assertSame(1, $this->countRows('coffee_contribution'));
        self::assertSame(1, $second->currentEnergy);
        self::assertSame(2, $second->remainingCoffees);
        $this->assertEditionState(energy: 1, coffeeCount: 1, version: 1);
    }

    public function testQuotaAllowsThreeThenRejectsTheFourth(): void
    {
        $this->createEdition();
        $visitor = $this->createVisitor('A');
        $serve = $this->serveCoffee();

        self::assertSame(2, $serve->serve($visitor, $this->friday, self::TZ, 'a1')->remainingCoffees);
        self::assertSame(1, $serve->serve($visitor, $this->friday, self::TZ, 'a2')->remainingCoffees);
        self::assertSame(0, $serve->serve($visitor, $this->friday, self::TZ, 'a3')->remainingCoffees);

        try {
            $serve->serve($visitor, $this->friday, self::TZ, 'a4');
            self::fail('The fourth coffee should have been rejected.');
        } catch (CoffeeLimitReached) {
            self::assertSame(3, $this->countRows('coffee_contribution'));
            $this->assertEditionState(energy: 3, coffeeCount: 3, version: 3);
        }
    }

    public function testTwoDistinctVisitorsBothApplyNoLostUpdate(): void
    {
        $this->createEdition();
        $serve = $this->serveCoffee();

        $serve->serve($this->createVisitor('A'), $this->friday, self::TZ, 'a1');
        $serve->serve($this->createVisitor('B'), $this->friday, self::TZ, 'b1');

        self::assertSame(2, $this->countRows('coffee_contribution'));
        $this->assertEditionState(energy: 2, coffeeCount: 2, version: 2);
    }

    private function serveCoffee(): ServeCoffee
    {
        return new ServeCoffee(
            new DoctrineTransactional($this->registry),
            new DoctrineFridayEditionRepository($this->registry),
            new DoctrineCoffeeContributionRepository($this->registry),
            new UlidIdentifierGenerator(),
            new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00')),
        );
    }

    private function createEdition(int $coffeeTarget = 100): void
    {
        $edition = FridayEdition::open(
            (new UlidIdentifierGenerator())->nextIdentifier(),
            $this->friday,
            self::TZ,
            $coffeeTarget,
            new \DateTimeImmutable('2026-07-03T10:00:00+02:00'),
        );
        (new DoctrineFridayEditionRepository($this->registry))->add($edition);
    }

    private function createVisitor(string $token): AnonymousVisitor
    {
        $visitor = AnonymousVisitor::register(
            (new UlidIdentifierGenerator())->nextIdentifier(),
            hash('sha256', $token),
            new \DateTimeImmutable('2026-07-03T10:00:00+02:00'),
        );
        (new DoctrineAnonymousVisitorRepository($this->registry))->add($visitor);

        return $visitor;
    }

    private function assertEditionState(int $energy, int $coffeeCount, int $version): void
    {
        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);
        $row = $connection->fetchAssociative('SELECT energy, coffee_count, energy_version FROM friday_edition');
        self::assertIsArray($row);
        self::assertSame($energy, (int) $row['energy']);
        self::assertSame($coffeeCount, (int) $row['coffee_count']);
        self::assertSame($version, (int) $row['energy_version']);
    }
}
