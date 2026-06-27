<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Http;

use App\Presentation\Http\Visitor\VisitorCookieResolver;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Bout-en-bout (env test, APP_FAKE_NOW = vendredi 2026-07-03) : l'endpoint
 * résout-ou-crée l'édition persistée, expose l'état (energy/coffeeCount = 0/0),
 * pose un cookie d'identité, et reste stable entre deux requêtes du même cookie.
 */
#[CoversNothing]
final class CurrentFridayEndpointTest extends DatabaseTestCase
{
    public function testResolvesPersistsAndStaysStableAcrossRequests(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        // 1re requête sans cookie : crée édition + visiteur + visite, pose le cookie.
        $first = $kernel->handle(Request::create('/api/friday/current'));
        self::assertSame(200, $first->getStatusCode());

        $payload = json_decode((string) $first->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame(
            [
                'active' => true,
                'date' => '2026-07-03',
                'timezone' => 'Europe/Paris',
                'status' => 'AWAKE',
                'energy' => 0,
                'coffeeCount' => 0,
                'visitor' => ['isNew' => true],
            ],
            $payload,
        );

        self::assertSame(1, $this->countRows('friday_edition'));
        self::assertSame(1, $this->countRows('anonymous_visitor'));
        self::assertSame(1, $this->countRows('friday_visit'));

        $cookieValue = $this->visitorCookieValue($first->headers->getCookies());
        self::assertNotNull($cookieValue);

        // 2e requête avec le même cookie : identité stable, rien de neuf en base.
        $request = Request::create('/api/friday/current');
        $request->cookies->set(VisitorCookieResolver::COOKIE_NAME, $cookieValue);
        $second = $kernel->handle($request);
        self::assertSame(200, $second->getStatusCode());

        $payload2 = json_decode((string) $second->getContent(), true);
        self::assertIsArray($payload2);
        self::assertSame(['isNew' => false], $payload2['visitor']);

        self::assertSame(1, $this->countRows('friday_edition'));
        self::assertSame(1, $this->countRows('anonymous_visitor'));
        self::assertSame(1, $this->countRows('friday_visit'));
    }

    /**
     * @param array<Cookie> $cookies
     */
    private function visitorCookieValue(array $cookies): ?string
    {
        foreach ($cookies as $cookie) {
            if (VisitorCookieResolver::COOKIE_NAME === $cookie->getName()) {
                return $cookie->getValue();
            }
        }

        return null;
    }
}
