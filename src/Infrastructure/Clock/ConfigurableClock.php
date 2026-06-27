<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Domain\Shared\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Horloge de développement / préproduction (§7.3, §7.4).
 *
 * Si `APP_FAKE_NOW` est défini, l'horloge est GELÉE sur cet instant pour simuler
 * un vendredi ; sinon elle délègue à l'horloge système. Cette classe n'est liée
 * à `ClockInterface` qu'en dev/préprod — JAMAIS en production, où la liaison
 * pointe directement sur `SystemClock` (neutralisation par construction, §7.4).
 *
 * Note invariant : le `new \DateTimeImmutable($fakeNow)` ci-dessous PARSE une
 * valeur de configuration explicite (un instant fixe), il ne lit pas l'horloge
 * murale — la lecture du temps réel reste l'apanage de `SystemClock`.
 */
final readonly class ConfigurableClock implements ClockInterface
{
    private ?\DateTimeImmutable $frozenAt;

    public function __construct(
        private ClockInterface $systemClock,
        string $fakeNow = '',
        ?LoggerInterface $logger = null,
    ) {
        $this->frozenAt = '' !== $fakeNow ? new \DateTimeImmutable($fakeNow) : null;

        if ($this->frozenAt instanceof \DateTimeImmutable) {
            $logger?->info('Horloge métier SIMULÉE via APP_FAKE_NOW.', [
                'app_fake_now' => $fakeNow,
                'resolved' => $this->frozenAt->format(\DateTimeInterface::ATOM),
            ]);
        }
    }

    public function now(): \DateTimeImmutable
    {
        return $this->frozenAt ?? $this->systemClock->now();
    }
}
