<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Clock;

use App\Infrastructure\Clock\ConfigurableClock;
use App\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

#[CoversClass(ConfigurableClock::class)]
final class ConfigurableClockTest extends TestCase
{
    public function testFreezesOnAppFakeNowWhenSet(): void
    {
        $clock = new ConfigurableClock(new SystemClock(), '2026-07-03T10:30:00+02:00');

        $first = $clock->now();
        $second = $clock->now();

        self::assertSame('2026-07-03T10:30:00+02:00', $first->format(\DateTimeInterface::ATOM));
        // Figée : deux lectures successives donnent le même instant.
        self::assertEquals($first, $second);
    }

    public function testDelegatesToSystemClockWhenEmpty(): void
    {
        $clock = new ConfigurableClock(new SystemClock(), '');

        $before = new \DateTimeImmutable('now');
        $now = $clock->now();
        $after = new \DateTimeImmutable('now');

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    public function testLogsOnceWhenSimulated(): void
    {
        $logger = $this->recordingLogger();

        new ConfigurableClock(new SystemClock(), '2026-07-03T10:30:00+02:00', $logger);

        self::assertCount(1, $logger->records);
        self::assertSame('info', $logger->records[0]['level']);
        self::assertArrayHasKey('app_fake_now', $logger->records[0]['context']);
    }

    public function testDoesNotLogWhenNotSimulated(): void
    {
        $logger = $this->recordingLogger();

        new ConfigurableClock(new SystemClock(), '', $logger);

        self::assertCount(0, $logger->records);
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: string, message: string, context: array<string, mixed>}>}
     */
    private function recordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }
}
