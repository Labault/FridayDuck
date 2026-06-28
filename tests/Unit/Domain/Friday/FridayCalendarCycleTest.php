<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Friday;

use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * `mostRecentFriday()` cible le vendredi écoulé (tâches du samedi, §25.1), à minuit
 * HEURE MURALE Europe/Paris — y compris autour d'une bascule DST.
 */
#[CoversClass(FridayCalendar::class)]
final class FridayCalendarCycleTest extends TestCase
{
    public function testMostRecentFridayOnSaturdayIsYesterdayAtParisMidnight(): void
    {
        $friday = $this->calendarAt('2026-07-04T01:00:00+02:00')->mostRecentFriday();

        self::assertSame('2026-07-03', $friday->format('Y-m-d'));
        self::assertSame('00:00:00', $friday->format('H:i:s'));
        self::assertSame('+02:00', $friday->format('P'));
    }

    public function testMostRecentFridayOnFridayIsToday(): void
    {
        self::assertSame('2026-07-03', $this->calendarAt('2026-07-03T20:00:00+02:00')->mostRecentFriday()->format('Y-m-d'));
    }

    public function testMostRecentFridayKeepsWallClockMidnightAroundDst(): void
    {
        // Bascule de printemps : dimanche 2026-03-29. Le samedi 2026-03-28 vise le
        // vendredi 2026-03-27, encore en CET (+01:00) — minuit MURAL préservé.
        $friday = $this->calendarAt('2026-03-28T05:00:00+01:00')->mostRecentFriday();

        self::assertSame('2026-03-27', $friday->format('Y-m-d'));
        self::assertSame('00:00:00', $friday->format('H:i:s'));
        self::assertSame('+01:00', $friday->format('P'));
    }

    private function calendarAt(string $now): FridayCalendar
    {
        return new FridayCalendar(new FrozenClock(new \DateTimeImmutable($now)), 'Europe/Paris');
    }
}
