<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Accessory;

use App\Domain\Accessory\AccessoryVoteSchedule;
use App\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * La fenêtre de vote est DÉRIVÉE DE L'HORLOGE (invariant A) : ouverte avant 14:00
 * Paris, fermée à 14:00:00 pile et au-delà — sans aucune colonne de statut.
 */
#[CoversClass(AccessoryVoteSchedule::class)]
final class AccessoryVoteScheduleTest extends TestCase
{
    private \DateTimeImmutable $friday;

    protected function setUp(): void
    {
        $this->friday = new \DateTimeImmutable('2026-07-03 00:00:00', new \DateTimeZone('Europe/Paris'));
    }

    public function testOpenBeforeTwoPM(): void
    {
        self::assertTrue($this->scheduleAt('2026-07-03T13:59:59+02:00')->isOpen($this->friday));
    }

    public function testClosedAtExactlyTwoPM(): void
    {
        self::assertFalse($this->scheduleAt('2026-07-03T14:00:00+02:00')->isOpen($this->friday));
    }

    public function testClosedAfterTwoPM(): void
    {
        self::assertFalse($this->scheduleAt('2026-07-03T14:00:01+02:00')->isOpen($this->friday));
    }

    public function testClosesAtIsTwoPMWallTimeParis(): void
    {
        $closesAt = $this->scheduleAt('2026-07-03T08:00:00+02:00')->closesAt($this->friday);

        self::assertSame('2026-07-03T14:00:00+02:00', $closesAt->format(\DateTimeInterface::ATOM));
    }

    private function scheduleAt(string $now): AccessoryVoteSchedule
    {
        return new AccessoryVoteSchedule(new FrozenClock(new \DateTimeImmutable($now)), 'Europe/Paris');
    }
}
