<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Domain\Shared\Clock\ClockInterface;

/**
 * Horloge figée (§7.3) — instant fixé à la construction.
 *
 * Support des tests : le temps ne s'écoule pas, `now()` renvoie toujours le même
 * instant. L'appelant fournit l'instant ; cette classe ne lit jamais l'horloge
 * réelle.
 */
final readonly class FrozenClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
