<?php

declare(strict_types=1);

namespace App\Domain\Friday;

/**
 * Édition hebdomadaire PERSISTÉE (§23.1) — agrégat racine de l'état collectif.
 *
 * PHP pur : aucun attribut/`use` Doctrine. Le mapping ORM est externe
 * (Infrastructure/Persistence/mapping). Doctrine hydrate par réflexion, sans
 * appeler le constructeur.
 *
 * Phase 2a-i : porteur d'état (énergie/compteurs à 0). La logique café/énergie
 * arrive en Phase 2a-ii. Les colonnes vote/conseil/gagnant viendront avec leurs
 * phases (migrations incrémentales).
 */
final class FridayEdition
{
    private function __construct(
        private string $id,
        private \DateTimeImmutable $fridayDate,
        private string $timezone,
        private EditionStatus $editionStatus,
        private int $energy,
        private int $energyVersion,
        private int $coffeeTarget,
        private int $coffeeCount,
        private int $overcaffeinationCount,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $closedAt,
    ) {
    }

    /**
     * Ouvre une édition à la volée pour un vendredi (§25.2) : énergie et
     * compteurs à zéro, cible de café issue de la configuration.
     */
    public static function open(
        string $id,
        \DateTimeImmutable $fridayDate,
        string $timezone,
        int $coffeeTarget,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            fridayDate: $fridayDate,
            timezone: $timezone,
            editionStatus: EditionStatus::Open,
            energy: 0,
            energyVersion: 0,
            coffeeTarget: $coffeeTarget,
            coffeeCount: 0,
            overcaffeinationCount: 0,
            createdAt: $now,
            closedAt: null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fridayDate(): \DateTimeImmutable
    {
        return $this->fridayDate;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function status(): EditionStatus
    {
        return $this->editionStatus;
    }

    public function energy(): int
    {
        return $this->energy;
    }

    public function energyVersion(): int
    {
        return $this->energyVersion;
    }

    public function coffeeTarget(): int
    {
        return $this->coffeeTarget;
    }

    public function coffeeCount(): int
    {
        return $this->coffeeCount;
    }

    public function overcaffeinationCount(): int
    {
        return $this->overcaffeinationCount;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }
}
