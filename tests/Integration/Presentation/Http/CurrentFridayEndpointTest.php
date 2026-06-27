<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Bout-en-bout (env test, APP_FAKE_NOW = vendredi 2026-07-03, §7.4) :
 * Presentation → Application → Domaine, sans base. Prouve aussi que la
 * simulation APP_FAKE_NOW est ACTIVE hors production.
 */
#[CoversNothing]
final class CurrentFridayEndpointTest extends KernelTestCase
{
    public function testReturnsSimulatedFridayState(): void
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(Request::create('/api/friday/current'));

        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame(
            [
                'active' => true,
                'date' => '2026-07-03',
                'timezone' => 'Europe/Paris',
                'status' => 'AWAKE',
            ],
            $payload,
        );
    }
}
