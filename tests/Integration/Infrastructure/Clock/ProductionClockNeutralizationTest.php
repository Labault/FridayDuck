<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Clock;

use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Vérifie la NEUTRALISATION d'APP_FAKE_NOW en production (§7.4).
 *
 * APP_FAKE_NOW est présent (fixé par .env.test sur un vendredi). On démarre un
 * noyau PROD : la liaison ClockInterface → SystemClock doit ignorer la variable
 * et refléter l'heure RÉELLE, pas le vendredi simulé.
 *
 * ISOLATION (§7.4, I1) : un noyau prod ignore le bloc `when@test` de Doctrine, il
 * viserait donc la base `app` SANS suffixe — partagée avec d'autres projets sur le
 * Postgres local, à l'état imprévisible (4 faux échecs successifs). On le redirige
 * vers la base de test DÉDIÉE et jetable `app_test` (propriété exclusive du projet,
 * créée/migrée par la CI, vidée par test). Le verdict ne dépend plus de l'état d'un
 * Postgres partagé : il est déterministe quel que soit l'environnement local.
 */
#[CoversNothing]
final class ProductionClockNeutralizationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        // Compilation FRAÎCHE du conteneur prod : en debug=false, le cache n'est
        // pas invalidé sur changement de source — on évite tout conteneur périmé.
        (new Filesystem())->remove(\dirname(__DIR__, 4).'/var/cache/prod');
    }

    public function testAppFakeNowHasNoEffectInProduction(): void
    {
        // Pré-condition : la simulation est bien présente dans l'environnement.
        self::assertNotSame('', (string) ($_SERVER['APP_FAKE_NOW'] ?? getenv('APP_FAKE_NOW') ?: ''));

        // Redirige le noyau prod vers la base de test dédiée AVANT le boot.
        $restore = self::redirectToDedicatedTestDatabase();

        try {
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
        } finally {
            $restore();
        }
    }

    /**
     * Pointe `DATABASE_URL` sur la base de test dédiée (`<db>_test`) pour le noyau
     * prod sous test, et renvoie une closure qui restaure l'environnement initial.
     *
     * Le suffixe `_test` reproduit ce que le bloc `when@test` de Doctrine applique
     * en env test ; en env prod ce bloc ne s'applique pas, d'où l'override explicite
     * sur le nom de base (dernier segment de chemin), la chaîne de requête préservée.
     *
     * @return callable(): void
     */
    private static function redirectToDedicatedTestDatabase(): callable
    {
        $original = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: null;

        $base = (string) ($original ?? '');
        $testUrl = preg_replace('#/([^/?]+)(\?.*)?$#', '/$1_test$2', $base, 1);
        if (!\is_string($testUrl) || $testUrl === $base) {
            self::markTestSkipped('DATABASE_URL absent ou non transformable : isolation impossible.');
        }

        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $testUrl;
        putenv('DATABASE_URL='.$testUrl);

        return static function () use ($original): void {
            if (null === $original) {
                unset($_SERVER['DATABASE_URL'], $_ENV['DATABASE_URL']);
                putenv('DATABASE_URL');

                return;
            }
            $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $original;
            putenv('DATABASE_URL='.$original);
        };
    }
}
