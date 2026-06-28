<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Http\Controller;

use App\Application\Health\DatabaseHealthInterface;
use App\Presentation\Http\Controller\HealthController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(HealthController::class)]
final class HealthControllerTest extends TestCase
{
    public function testLivenessReturns200PlainOkWithoutTouchingDatabase(): void
    {
        // Base DOWN : la liveness ne la consulte pas et reste verte.
        $controller = new HealthController($this->databaseHealth(false), '1.2.3');

        $response = $controller->live();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('OK', $response->getContent());
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function testReadinessReturns200AndOkStatusWhenDatabaseIsUp(): void
    {
        $controller = new HealthController($this->databaseHealth(true), '1.2.3');

        $response = $controller->ready();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","db":"up","version":"1.2.3"}',
            (string) $response->getContent(),
        );
    }

    public function testReadinessReturns503WhenDatabaseIsDown(): void
    {
        // Version vide → repli sur « dev ».
        $controller = new HealthController($this->databaseHealth(false), '');

        $response = $controller->ready();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","db":"down","version":"dev"}',
            (string) $response->getContent(),
        );
    }

    private function databaseHealth(bool $available): DatabaseHealthInterface
    {
        return new class($available) implements DatabaseHealthInterface {
            public function __construct(private bool $available)
            {
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        };
    }
}
