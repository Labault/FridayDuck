<?php

declare(strict_types=1);

namespace App\Presentation\Http\Visitor;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * Le HASH d'identité à transmettre à l'Application, et — si le cookie était
 * absent — le cookie à poser sur la réponse. Le jeton brut ne quitte jamais la
 * Présentation (§27.2).
 */
final readonly class ResolvedVisitorCookie
{
    public function __construct(
        public string $hash,
        public ?Cookie $issued,
    ) {
    }
}
