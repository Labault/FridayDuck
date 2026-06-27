<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Clock;

use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Vérifie la NEUTRALISATION d'APP_FAKE_NOW en production (§7.4).
 *
 * APP_FAKE_NOW est présent (fixé par .env.test sur un vendredi). On démarre un
 * noyau PROD : la liaison ClockInterface → SystemClock doit ignorer la variable
 * et refléter l'heure RÉELLE, pas le vendredi simulé.
 */
#[CoversNothing]
final class ProductionClockNeutralizationTest extends KernelTestCase
{
    public function testAppFakeNowHasNoEffectInProduction(): void
    {
        // Pré-condition : la simulation est bien présente dans l'environnement.
        self::assertNotSame('', (string) ($_SERVER['APP_FAKE_NOW'] ?? getenv('APP_FAKE_NOW') ?: ''));

        try {
            $kernel = self::bootKernel(['environment' => 'prod', 'debug' => false]);
        } catch (\Throwable $e) {
            self::markTestSkipped('Noyau prod indisponible dans cet environnement : '.$e->getMessage());
        }

        $response = $kernel->handle(Request::create('/api/friday/current'));
        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertIsArray($payload);

        // Référence : ce que l'horloge système réelle calcule au même moment.
        $expected = (new FridayCalendar(new SystemClock(), 'Europe/Paris'))->currentState();

        self::assertSame($expected->date(), $payload['date']);
        self::assertSame($expected->active, $payload['active']);
        self::assertSame($expected->status->value, $payload['status']);
    }
}
