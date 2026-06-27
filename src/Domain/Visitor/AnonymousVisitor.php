<?php

declare(strict_types=1);

namespace App\Domain\Visitor;

/**
 * Identité anonyme (§23.2, §27.1) — PHP pur, mapping ORM externe.
 *
 * Aucune donnée personnelle : on ne stocke qu'un HASH de l'identifiant du cookie
 * (jamais le cookie en clair, §27.2). Les compteurs de visite sont incrémentés
 * atomiquement côté base (pas de lecture-modification-écriture).
 */
final class AnonymousVisitor
{
    private function __construct(
        private string $id,
        private string $anonymousIdentifierHash,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $lastSeenAt,
        private int $totalVisits,
    ) {
    }

    public static function register(
        string $id,
        string $anonymousIdentifierHash,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            anonymousIdentifierHash: $anonymousIdentifierHash,
            createdAt: $now,
            lastSeenAt: $now,
            totalVisits: 1,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function anonymousIdentifierHash(): string
    {
        return $this->anonymousIdentifierHash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function totalVisits(): int
    {
        return $this->totalVisits;
    }
}
