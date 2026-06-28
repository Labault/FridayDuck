<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Vote d'un visiteur pour un accessoire (§23.6) — PHP pur, mapping externe.
 *
 * Entité en écriture unique : un vote est IMMUABLE (pas d'`updated_at`, §23.6).
 * UNIQUE(friday_edition_id, visitor_id) garantit « un seul vote par visiteur » :
 * un re-vote ne met jamais à jour, il est refusé (ALREADY_VOTED, invariant B).
 */
final readonly class AccessoryVote
{
    private function __construct(
        private string $id,
        private string $fridayEditionId,
        private string $visitorId,
        private string $accessoryId,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function cast(
        string $id,
        string $fridayEditionId,
        string $visitorId,
        string $accessoryId,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $fridayEditionId, $visitorId, $accessoryId, $now);
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

    public function accessoryId(): string
    {
        return $this->accessoryId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
