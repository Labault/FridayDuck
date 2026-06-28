<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Une des TROIS options de vote d'une édition (§23.5) — PHP pur, mapping externe.
 *
 * Référence l'accessoire PAR IDENTITÉ. `voteCount` est un compteur DÉNORMALISÉ
 * (§23.5) incrémenté sous le verrou d'édition (race-safe, comme coffee_count / D).
 */
final class FridayAccessoryOption
{
    private function __construct(
        private readonly string $id,
        private readonly string $fridayEditionId,
        private readonly string $accessoryId,
        private readonly int $displayOrder,
        private int $voteCount,
    ) {
    }

    public static function create(
        string $id,
        string $fridayEditionId,
        string $accessoryId,
        int $displayOrder,
    ): self {
        return new self($id, $fridayEditionId, $accessoryId, $displayOrder, 0);
    }

    /**
     * Enregistre UN vote. À appeler sous le verrou d'édition : la sérialisation
     * garantit l'absence de lost update sur le compteur (invariant D).
     */
    public function recordVote(): void
    {
        ++$this->voteCount;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fridayEditionId(): string
    {
        return $this->fridayEditionId;
    }

    public function accessoryId(): string
    {
        return $this->accessoryId;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function voteCount(): int
    {
        return $this->voteCount;
    }
}
