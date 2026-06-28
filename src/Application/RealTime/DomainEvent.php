<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * Événement de domaine diffusable en temps réel (§24.5).
 *
 * `type()` est le discriminant lu par le front (4b) pour router l'événement ;
 * `payload()` est sa charge GLOBALE (invariant B : jamais d'état par-visiteur).
 */
interface DomainEvent
{
    public function type(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
