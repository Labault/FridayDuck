<?php

declare(strict_types=1);

namespace App\Presentation\Http\Visitor;

use App\Domain\Shared\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Identité anonyme par cookie (§23.2, §27).
 *
 * Lit le jeton du cookie ou en émet un nouveau (aléatoire). Ne renvoie à
 * l'Application que le HASH (SHA-256) du jeton — le jeton brut n'est JAMAIS
 * stocké en clair. Cookie : HttpOnly, SameSite=Lax, Secure si HTTPS, Path=/,
 * durée d'un an.
 */
final readonly class VisitorCookieResolver
{
    public const string COOKIE_NAME = 'cdv_visitor';

    private const string COOKIE_LIFETIME = '+1 year';

    public function __construct(private ClockInterface $clock)
    {
    }

    public function readOrIssue(Request $request): ResolvedVisitorCookie
    {
        $raw = $request->cookies->get(self::COOKIE_NAME);
        if (\is_string($raw) && '' !== $raw) {
            return new ResolvedVisitorCookie($this->hash($raw), null);
        }

        $raw = bin2hex(random_bytes(32));
        $cookie = Cookie::create(self::COOKIE_NAME, $raw)
            ->withExpires($this->clock->now()->modify(self::COOKIE_LIFETIME))
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX);

        return new ResolvedVisitorCookie($this->hash($raw), $cookie);
    }

    private function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
