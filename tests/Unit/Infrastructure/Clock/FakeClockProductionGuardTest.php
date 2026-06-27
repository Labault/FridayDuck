<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Clock;

use App\Infrastructure\Clock\FakeClockProductionGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(FakeClockProductionGuard::class)]
final class FakeClockProductionGuardTest extends TestCase
{
    public function testWarnsInProductionWhenFakeNowIsSet(): void
    {
        $logger = $this->recordingLogger();
        $guard = new FakeClockProductionGuard('prod', '2026-07-03T10:30:00+02:00', $logger);

        $guard($this->requestEvent());

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
    }

    public function testWarnsOnlyOnce(): void
    {
        $logger = $this->recordingLogger();
        $guard = new FakeClockProductionGuard('prod', '2026-07-03T10:30:00+02:00', $logger);

        $guard($this->requestEvent());
        $guard($this->requestEvent());

        self::assertCount(1, $logger->records);
    }

    public function testSilentInProductionWhenFakeNowIsEmpty(): void
    {
        $logger = $this->recordingLogger();
        $guard = new FakeClockProductionGuard('prod', '', $logger);

        $guard($this->requestEvent());

        self::assertCount(0, $logger->records);
    }

    public function testSilentOutsideProductionEvenWhenFakeNowIsSet(): void
    {
        $logger = $this->recordingLogger();
        $guard = new FakeClockProductionGuard('dev', '2026-07-03T10:30:00+02:00', $logger);

        $guard($this->requestEvent());

        self::assertCount(0, $logger->records);
    }

    private function requestEvent(): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
        );
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
