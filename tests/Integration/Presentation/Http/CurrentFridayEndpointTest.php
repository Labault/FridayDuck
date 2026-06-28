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
        self::assertSame('2026-07-03', $payload['date']);
        self::assertTrue($payload['active']);
        self::assertSame('AWAKE', $payload['status']);
        self::assertSame(0, $payload['energy']);
        self::assertSame(0, $payload['energyVersion']);
        self::assertSame(0, $payload['coffeeCount']);
        self::assertSame(['isNew' => true, 'remainingCoffees' => 3, 'hasVoted' => false, 'votedAccessory' => null, 'adviceReaction' => null], $payload['visitor']);

        // Bloc vote (§10) : ouvert, clôture à 14:00, exactement 3 options, pas de gagnant.
        self::assertIsArray($payload['vote']);
        self::assertTrue($payload['vote']['open']);
        self::assertSame('2026-07-03T14:00:00+02:00', $payload['vote']['closesAt']);
        self::assertNull($payload['vote']['winner']);
        self::assertSame(0, $payload['vote']['resultsSequence']);
        self::assertCount(3, $payload['vote']['options']);

        // Bloc conseil (§11) : texte du jour + compteurs de réactions à zéro.
        self::assertIsArray($payload['advice']);
        self::assertNotSame('', $payload['advice']['text']);
        self::assertSame(0, $payload['advice']['adviceSequence']);
        self::assertSame(['CONCERNING' => 0, 'ALREADY_DONE' => 0, 'TAKING_NOTES' => 0], $payload['advice']['reactions']);

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
        self::assertSame(['isNew' => false, 'remainingCoffees' => 3, 'hasVoted' => false, 'votedAccessory' => null, 'adviceReaction' => null], $payload2['visitor']);

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
