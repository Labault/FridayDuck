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
    public function testReturns200AndOkStatusWhenDatabaseIsUp(): void
    {
        $controller = new HealthController($this->databaseHealth(true), '1.2.3');

        $response = $controller();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","version":"1.2.3","checks":{"database":"up"}}',
            (string) $response->getContent(),
        );
    }

    public function testReturns503AndDegradedStatusWhenDatabaseIsDown(): void
    {
        // Version vide → repli sur « dev ».
        $controller = new HealthController($this->databaseHealth(false), '');

        $response = $controller();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"degraded","version":"dev","checks":{"database":"down"}}',
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
