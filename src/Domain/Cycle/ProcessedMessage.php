<?php

declare(strict_types=1);

namespace App\Domain\Cycle;

/**
 * Trace d'un message de cycle déjà traité (§25.3) — PHP pur, mapping externe.
 *
 * La clé (`friday-open:<date>`, `accessory-winner:<date>`, …) est l'identité :
 * son unicité garantit qu'une annonce de cycle n'est émise qu'UNE FOIS, même si
 * le message est rejoué (retry, ou Scheduler + rattrapage).
 */
final readonly class ProcessedMessage
{
    private function __construct(
        private string $messageKey,
        private \DateTimeImmutable $processedAt,
    ) {
    }

    public static function record(string $messageKey, \DateTimeImmutable $now): self
    {
        return new self($messageKey, $now);
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    public function processedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
