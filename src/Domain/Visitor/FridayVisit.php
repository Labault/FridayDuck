<?php

declare(strict_types=1);

namespace App\Domain\Visitor;

/**
 * Visite d'un visiteur à une édition (§23.9) — PHP pur, mapping ORM externe.
 *
 * Référence les agrégats Édition et Visiteur PAR IDENTITÉ (pas par objet),
 * conformément aux bonnes pratiques DDD. Un visiteur a au plus une visite par
 * édition : UNIQUE(friday_edition_id, visitor_id), upsert à chaque passage.
 */
final class FridayVisit
{
    private function __construct(
        private string $id,
        private string $fridayEditionId,
        private string $visitorId,
        private \DateTimeImmutable $firstSeenAt,
        private \DateTimeImmutable $lastSeenAt,
    ) {
    }

    public static function start(
        string $id,
        string $fridayEditionId,
        string $visitorId,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            fridayEditionId: $fridayEditionId,
            visitorId: $visitorId,
            firstSeenAt: $now,
            lastSeenAt: $now,
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

    public function firstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }
}
