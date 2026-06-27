<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Domain\Shared\Clock\ClockInterface;

/**
 * Horloge de production (§7.3).
 *
 * SEUL endroit autorisé à lire l'horloge réelle via `new \DateTimeImmutable()`.
 * En production, `ClockInterface` est liée à cette implémentation : `APP_FAKE_NOW`
 * n'a donc aucun effet (§7.4).
 */
final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}
