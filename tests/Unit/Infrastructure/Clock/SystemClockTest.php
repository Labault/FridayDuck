<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Clock;

use App\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testReturnsCurrentInstant(): void
    {
        $clock = new SystemClock();

        $before = new \DateTimeImmutable('now');
        $now = $clock->now();
        $after = new \DateTimeImmutable('now');

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    public function testIgnoresAppFakeNowEntirely(): void
    {
        // Même si APP_FAKE_NOW pointe sur l'an 2000, SystemClock lit l'horloge
        // réelle : c'est la neutralisation par construction de la prod (§7.4).
        $restore = getenv('APP_FAKE_NOW');
        putenv('APP_FAKE_NOW=2000-01-01T00:00:00+00:00');

        try {
            $now = (new SystemClock())->now();
            self::assertNotSame('2000', $now->format('Y'));
            self::assertGreaterThan(new \DateTimeImmutable('2020-01-01'), $now);
        } finally {
            if (false === $restore) {
                putenv('APP_FAKE_NOW');
            } else {
                putenv('APP_FAKE_NOW='.$restore);
            }
        }
    }
}
