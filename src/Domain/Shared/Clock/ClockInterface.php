<?php

declare(strict_types=1);

namespace App\Domain\Shared\Clock;

/**
 * Horloge injectable — unique source de vérité temporelle (§7.1, §7.3).
 *
 * Aucun service de Domaine n'appelle `new \DateTimeImmutable()` : le temps passe
 * EXCLUSIVEMENT par ce port. Seul `SystemClock` (Infrastructure) lit l'horloge
 * réelle.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
