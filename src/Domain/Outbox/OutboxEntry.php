<?php

declare(strict_types=1);

namespace App\Domain\Outbox;

/**
 * Ligne de l'outbox transactionnel (§20.6) — PHP pur, mapping externe.
 *
 * Écrite dans la MÊME transaction que la mutation métier (invariant A) : si
 * celle-ci rollback, l'événement n'existe pas. Un relais idempotent la publie
 * ensuite sur Mercure et marque `publishedAt` (invariant B), une seule fois.
 *
 * `id` est une identité monotone assignée par la base (IDENTITY) au flush : il
 * EST l'ordre d'écriture (invariant C). Les événements d'une même édition étant
 * sérialisés par le verrou de la ligne édition, leurs `id` sont croissants dans
 * l'ordre causal — le relais publie par `friday_date` puis `id` croissant.
 */
final class OutboxEntry
{
    // 0 tant que non persistée ; la base (IDENTITY) assigne la vraie valeur au
    // flush (Doctrine exclut les colonnes IDENTITY de l'INSERT). Cet entier EST
    // l'ordre d'écriture/relais.
    private int $id = 0;

    private function __construct(
        private readonly string $fridayDate,
        private readonly string $type,
        private readonly string $payload,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $publishedAt,
        private int $attempts,
        private readonly ?string $traceparent,
    ) {
    }

    /**
     * @param ?string $traceparent contexte de trace W3C capturé à l'écriture (§26.2,
     *                             propagation) — restauré par le relais à la publication
     */
    public static function pending(string $fridayDate, string $type, string $payload, \DateTimeImmutable $now, ?string $traceparent = null): self
    {
        return new self($fridayDate, $type, $payload, $now, null, 0, $traceparent);
    }

    /**
     * Marque la ligne comme publiée (relais, invariant B) — jamais republiée ensuite.
     */
    public function markPublished(\DateTimeImmutable $now): void
    {
        $this->publishedAt = $now;
    }

    /**
     * Une tentative de publication a échoué (hub down) : la ligne reste non publiée,
     * le compteur sert au seuil de bascule en file d'échec (§25.4) et au diagnostic.
     */
    public function recordFailedAttempt(): void
    {
        ++$this->attempts;
    }

    public function isPublished(): bool
    {
        return $this->publishedAt instanceof \DateTimeImmutable;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function fridayDate(): string
    {
        return $this->fridayDate;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function traceparent(): ?string
    {
        return $this->traceparent;
    }
}
