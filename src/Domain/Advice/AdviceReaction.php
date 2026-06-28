<?php

declare(strict_types=1);

namespace App\Domain\Advice;

/**
 * Réaction d'un visiteur au conseil (§23.8) — PHP pur, mapping externe.
 *
 * MUTABLE (contrairement au vote) : un visiteur peut CHANGER sa réaction
 * (`changeTo`, met à jour `updatedAt`). UNIQUE(friday_edition_id, visitor_id)
 * garantit une seule ligne par visiteur ; le changement est un UPDATE atomique.
 */
final class AdviceReaction
{
    private function __construct(
        private readonly string $id,
        private readonly string $fridayEditionId,
        private readonly string $visitorId,
        private AdviceReactionType $adviceReactionType,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function record(
        string $id,
        string $fridayEditionId,
        string $visitorId,
        AdviceReactionType $adviceReactionType,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $fridayEditionId, $visitorId, $adviceReactionType, $now, $now);
    }

    /**
     * Change la réaction (§11.3) — à appeler sous le verrou d'édition, en miroir
     * du swap atomique des compteurs (invariant C).
     */
    public function changeTo(AdviceReactionType $adviceReactionType, \DateTimeImmutable $now): void
    {
        $this->adviceReactionType = $adviceReactionType;
        $this->updatedAt = $now;
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

    public function reaction(): AdviceReactionType
    {
        return $this->adviceReactionType;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
