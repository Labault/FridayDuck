<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Http;

use App\Presentation\Http\Visitor\VisitorCookieResolver;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Bout-en-bout POST /api/friday/current/coffees (env test, vendredi 2026-07-03).
 */
#[CoversNothing]
final class ServeCoffeeEndpointTest extends DatabaseTestCase
{
    public function testMissingIdempotencyKeyIsRejected(): void
    {
        $response = $this->kernel()->handle(Request::create('/api/friday/current/coffees', 'POST'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('INVALID_IDEMPOTENCY_KEY', $this->error($response));
        self::assertSame(0, $this->countRows('coffee_contribution'));
    }

    public function testServesACoffeeThenReplaysIdempotently(): void
    {
        $first = $this->kernel()->handle($this->coffeeRequest('action-1'));
        self::assertSame(200, $first->getStatusCode());

        $body = json_decode((string) $first->getContent(), true);
        self::assertIsArray($body);
        self::assertTrue($body['accepted']);
        self::assertSame(1, $body['currentEnergy']);
        self::assertSame(2, $body['remainingCoffeesForVisitor']);
        self::assertSame(1, $this->countRows('coffee_contribution'));

        // Même clé + même cookie → rejeu : 200, aucune nouvelle contribution.
        $second = $this->kernel()->handle($this->coffeeRequest('action-1'));
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(1, $this->countRows('coffee_contribution'));

        $secondBody = json_decode((string) $second->getContent(), true);
        self::assertIsArray($secondBody);
        self::assertSame(1, $secondBody['currentEnergy']);
        self::assertSame(2, $secondBody['remainingCoffeesForVisitor']);
    }

    public function testQuotaReturnsTooManyRequestsOnTheFourth(): void
    {
        foreach (['a1', 'a2', 'a3'] as $action) {
            self::assertSame(200, $this->kernel()->handle($this->coffeeRequest($action))->getStatusCode());
        }

        $fourth = $this->kernel()->handle($this->coffeeRequest('a4'));
        self::assertSame(429, $fourth->getStatusCode());
        self::assertSame('COFFEE_LIMIT_REACHED', $this->error($fourth));
        self::assertSame(3, $this->countRows('coffee_contribution'));
    }

    private function kernel(): KernelInterface
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return $kernel;
    }

    private function coffeeRequest(string $actionId, string $cookie = 'fixed-visitor-token'): Request
    {
        $request = Request::create('/api/friday/current/coffees', 'POST');
        $request->headers->set('Idempotency-Key', $actionId);
        $request->cookies->set(VisitorCookieResolver::COOKIE_NAME, $cookie);

        return $request;
    }

    private function error(\Symfony\Component\HttpFoundation\Response $response): string
    {
        $body = json_decode((string) $response->getContent(), true);
        self::assertIsArray($body);

        return \is_string($body['error'] ?? null) ? $body['error'] : '';
    }
}
