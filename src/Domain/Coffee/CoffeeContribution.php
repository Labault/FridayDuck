<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * Café offert par un visiteur à une édition (§23.3) — PHP pur, mapping externe.
 *
 * Référence l'édition et le visiteur PAR IDENTITÉ. La clé d'idempotence est
 * composée côté serveur (§8.6) ; sa contrainte d'unicité est le filet de sécurité
 * ultime contre le double comptage. `energyBefore`/`energyAfter` servent l'audit
 * et le rejeu (§25.3). Entité en écriture unique (jamais mutée après insertion).
 */
final class CoffeeContribution
{
    private function __construct(
        private string $id,
        private string $fridayEditionId,
        private string $visitorId,
        private string $idempotencyKey,
        private string $clientActionId,
        private int $energyBefore,
        private int $energyAfter,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        string $id,
        string $fridayEditionId,
        string $visitorId,
        string $idempotencyKey,
        string $clientActionId,
        int $energyBefore,
        int $energyAfter,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            fridayEditionId: $fridayEditionId,
            visitorId: $visitorId,
            idempotencyKey: $idempotencyKey,
            clientActionId: $clientActionId,
            energyBefore: $energyBefore,
            energyAfter: $energyAfter,
            createdAt: $now,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fridayEditionId(): string
    {
        return $this->fridayEditionId;
    }

    public function visitorId(): string
    {
        return $this->visitorId;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function clientActionId(): string
    {
        return $this->clientActionId;
    }

    public function energyBefore(): int
    {
        return $this->energyBefore;
    }

    public function energyAfter(): int
    {
        return $this->energyAfter;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
