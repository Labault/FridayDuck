<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Http;

use App\Presentation\Http\Visitor\VisitorCookieResolver;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Bout-en-bout du vote (env test, vendredi 2026-07-03 10:30 → vote OUVERT).
 */
#[CoversNothing]
final class CastVoteEndpointTest extends DatabaseTestCase
{
    public function testGetExposesAnOpenVoteWithThreeOptions(): void
    {
        $body = $this->json($this->kernel()->handle($this->currentRequest()));

        self::assertIsArray($body['vote']);
        self::assertTrue($body['vote']['open']);
        self::assertSame('2026-07-03T14:00:00+02:00', $body['vote']['closesAt']);
        self::assertNull($body['vote']['winner']);
        self::assertIsArray($body['vote']['options']);
        self::assertCount(3, $body['vote']['options']);
        self::assertIsArray($body['visitor']);
        self::assertFalse($body['visitor']['hasVoted']);
    }

    public function testVoteIsAcceptedThenRejectedAsAlreadyVoted(): void
    {
        $code = $this->firstOptionCode();

        $first = $this->kernel()->handle($this->voteRequest($code));
        self::assertSame(200, $first->getStatusCode());
        $firstBody = $this->json($first);
        self::assertTrue($firstBody['accepted']);
        self::assertSame($code, $firstBody['accessory']);
        self::assertSame(1, $firstBody['resultsSequence']);
        self::assertSame(1, $this->countRows('accessory_vote'));

        $second = $this->kernel()->handle($this->voteRequest($code));
        self::assertSame(409, $second->getStatusCode());
        self::assertSame('ALREADY_VOTED', $this->json($second)['reason']);
        self::assertSame(1, $this->countRows('accessory_vote'));
    }

    public function testHasVotedIsReflectedInTheGetAfterVoting(): void
    {
        $this->kernel()->handle($this->voteRequest($this->firstOptionCode()));

        $body = $this->json($this->kernel()->handle($this->currentRequest()));
        self::assertIsArray($body['visitor']);
        self::assertTrue($body['visitor']['hasVoted']);
    }

    public function testUnknownAccessoryIsUnprocessable(): void
    {
        $response = $this->kernel()->handle($this->voteRequest('not_a_real_accessory'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('INVALID_ACCESSORY', $this->json($response)['reason']);
        self::assertSame(0, $this->countRows('accessory_vote'));
    }

    public function testMissingAccessoryIsUnprocessable(): void
    {
        $request = Request::create('/api/friday/current/accessory-votes', 'POST', content: '{}');
        $request->cookies->set(VisitorCookieResolver::COOKIE_NAME, 'voter-token');

        $response = $this->kernel()->handle($request);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('INVALID_ACCESSORY', $this->json($response)['reason']);
    }

    private function firstOptionCode(): string
    {
        $body = $this->json($this->kernel()->handle($this->currentRequest()));
        self::assertIsArray($body['vote']);
        $options = $body['vote']['options'];
        self::assertIsArray($options);
        self::assertArrayHasKey(0, $options);
        $code = $options[0]['code'];
        self::assertIsString($code);

        return $code;
    }

    private function currentRequest(string $cookie = 'voter-token'): Request
    {
        $request = Request::create('/api/friday/current', 'GET');
        $request->cookies->set(VisitorCookieResolver::COOKIE_NAME, $cookie);

        return $request;
    }

    private function voteRequest(string $code, string $cookie = 'voter-token'): Request
    {
        $request = Request::create('/api/friday/current/accessory-votes', 'POST', content: json_encode(['accessory' => $code]));
        $request->cookies->set(VisitorCookieResolver::COOKIE_NAME, $cookie);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $body = json_decode((string) $response->getContent(), true);
        self::assertIsArray($body);

        return $body;
    }

    private function kernel(): KernelInterface
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return $kernel;
    }
}
