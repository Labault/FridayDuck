<?php

declare(strict_types=1);

namespace App\Domain\Advice;

/**
 * Conseil catastrophique du catalogue (§23.7, §11) — PHP pur, mapping externe.
 *
 * `slug` est l'identifiant éditorial stable (clé de sélection déterministe §10.4
 * réutilisée). Les conseils sont écrits et validés À LA MAIN (§11.5, pas d'IA).
 */
final readonly class Advice
{
    private function __construct(
        private string $id,
        private string $text,
        private string $slug,
        private bool $active,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function write(string $id, string $text, string $slug, \DateTimeImmutable $now): self
    {
        return new self($id, $text, $slug, true, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function slug(): string
    {
        return $this->slug;
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
