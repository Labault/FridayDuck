<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Accessoire du catalogue (§23.4) — PHP pur, mapping externe.
 *
 * `code` est l'identifiant métier stable (clé de sélection déterministe §10.4 et
 * de vote §24.3). `svgGroupId`/`entranceSequence` sont des métadonnées pour le
 * front 4b (groupe SVG porteur, séquence de révélation) — la donnée suffit ici.
 */
final readonly class Accessory
{
    private function __construct(
        private string $id,
        private string $code,
        private string $label,
        private string $description,
        private AccessorySlot $accessorySlot,
        private string $svgGroupId,
        private string $entranceSequence,
        private bool $active,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function define(
        string $id,
        string $code,
        string $label,
        string $description,
        AccessorySlot $accessorySlot,
        string $svgGroupId,
        string $entranceSequence,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $code, $label, $description, $accessorySlot, $svgGroupId, $entranceSequence, true, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function slot(): AccessorySlot
    {
        return $this->accessorySlot;
    }

    public function svgGroupId(): string
    {
        return $this->svgGroupId;
    }

    public function entranceSequence(): string
    {
        return $this->entranceSequence;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
