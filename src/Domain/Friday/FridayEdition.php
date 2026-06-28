<?php

declare(strict_types=1);

namespace App\Domain\Friday;

use App\Domain\Advice\AdviceReactionType;

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
        private int $resultsSequence,
        private ?string $winnerAccessoryCode,
        private ?string $adviceId,
        private int $adviceSequence,
        private int $concerningCount,
        private int $alreadyDoneCount,
        private int $takingNotesCount,
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
            resultsSequence: 0,
            winnerAccessoryCode: null,
            adviceId: null,
            adviceSequence: 0,
            concerningCount: 0,
            alreadyDoneCount: 0,
            takingNotesCount: 0,
        );
    }

    /**
     * Fige le conseil du vendredi (§11.1) — IMMUABLE : un changement de catalogue
     * en plein vendredi ne déplace pas le conseil du jour. Sous le verrou d'édition.
     */
    public function selectAdvice(string $adviceId): void
    {
        if (null !== $this->adviceId) {
            throw new \LogicException('Le conseil du vendredi est déjà figé (§11.1).');
        }

        $this->adviceId = $adviceId;
    }

    /**
     * Première réaction d'un visiteur : incrémente le compteur du type + bumpe la
     * séquence d'advice (DISTINCTE d'energy_version et de la séquence de vote).
     * Sous le verrou d'édition (D).
     */
    public function recordAdviceReaction(AdviceReactionType $adviceReactionType): void
    {
        $this->adjustAdviceReaction($adviceReactionType, 1);
        ++$this->adviceSequence;
    }

    /**
     * Changement de réaction : décrément de l'ancien type, incrément du nouveau —
     * ATOMIQUE sous le verrou (§11.3, invariants C/D). Compteurs jamais négatifs.
     */
    public function changeAdviceReaction(
        AdviceReactionType $from,
        AdviceReactionType $to,
    ): void {
        $this->adjustAdviceReaction($from, -1);
        $this->adjustAdviceReaction($to, 1);
        ++$this->adviceSequence;
    }

    private function adjustAdviceReaction(AdviceReactionType $adviceReactionType, int $delta): void
    {
        $next = max(0, $this->adviceReactionCount($adviceReactionType) + $delta);
        match ($adviceReactionType) {
            AdviceReactionType::Concerning => $this->concerningCount = $next,
            AdviceReactionType::AlreadyDone => $this->alreadyDoneCount = $next,
            AdviceReactionType::TakingNotes => $this->takingNotesCount = $next,
        };
    }

    public function adviceReactionCount(AdviceReactionType $adviceReactionType): int
    {
        return match ($adviceReactionType) {
            AdviceReactionType::Concerning => $this->concerningCount,
            AdviceReactionType::AlreadyDone => $this->alreadyDoneCount,
            AdviceReactionType::TakingNotes => $this->takingNotesCount,
        };
    }

    public function adviceId(): ?string
    {
        return $this->adviceId;
    }

    public function hasAdvice(): bool
    {
        return null !== $this->adviceId;
    }

    public function adviceSequence(): int
    {
        return $this->adviceSequence;
    }

    public function concerningCount(): int
    {
        return $this->concerningCount;
    }

    public function alreadyDoneCount(): int
    {
        return $this->alreadyDoneCount;
    }

    public function takingNotesCount(): int
    {
        return $this->takingNotesCount;
    }

    /**
     * Enregistre UN vote accepté : bumpe la séquence de résultats (§24.5), jeton
     * anti-régression DISTINCT d'`energyVersion`. Sous le verrou d'édition (D).
     */
    public function recordAccessoryVote(): void
    {
        ++$this->resultsSequence;
    }

    /**
     * Fige le gagnant du vote (§10.1) — IMMUABLE : une fois choisi, il ne change
     * plus. Appelé une seule fois, sous le verrou d'édition (résolution race-safe).
     */
    public function selectWinner(string $accessoryCode): void
    {
        if (null !== $this->winnerAccessoryCode) {
            throw new \LogicException('Le gagnant du vote est déjà figé (§10.1).');
        }

        $this->winnerAccessoryCode = $accessoryCode;
    }

    public function resultsSequence(): int
    {
        return $this->resultsSequence;
    }

    public function winnerAccessoryCode(): ?string
    {
        return $this->winnerAccessoryCode;
    }

    public function hasWinner(): bool
    {
        return null !== $this->winnerAccessoryCode;
    }

    /**
     * Enregistre UN café accepté et recalcule l'état d'énergie (§8.3/§8.4).
     *
     * L'énergie est une fonction DÉTERMINISTE de l'état accumulé :
     * `energy = min(100, floor(coffeeCount / coffeeTarget * 100))`. Au-delà du
     * plafond, l'énergie reste à 100 et chaque café supplémentaire alimente le
     * compteur de surcaféination. `energyVersion` est incrémenté à chaque café
     * (jeton monotone pour la couche temps réel — Phase 3).
     *
     * À appeler dans la transaction verrouillée (§8.2/D) : la sérialisation par
     * verrou de ligne garantit l'absence de lost update sur le compteur.
     */
    public function recordCoffee(): void
    {
        ++$this->coffeeCount;
        $this->energy = min(100, intdiv($this->coffeeCount * 100, $this->coffeeTarget));
        $this->overcaffeinationCount = max(0, $this->coffeeCount - $this->coffeeTarget);
        ++$this->energyVersion;
    }

    /**
     * Ferme l'édition (samedi minuit, §12.5) — fait progresser le STATUT (un
     * ENREGISTREMENT, pas une autorité : aucune décision runtime ne le lit, §25.2).
     * Idempotent.
     */
    public function close(\DateTimeImmutable $now): void
    {
        if (EditionStatus::Closed === $this->editionStatus) {
            return;
        }

        $this->editionStatus = EditionStatus::Closed;
        $this->closedAt = $now;
    }

    public function isClosed(): bool
    {
        return EditionStatus::Closed === $this->editionStatus;
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
