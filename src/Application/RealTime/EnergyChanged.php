<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * §24.5 `ENERGY_CHANGED` — énergie globale recalculée après un café accepté.
 *
 * Charge MINIMALE (invariant B) : énergie, version (jeton anti-régression §20.4)
 * et l'identifiant d'action client du café (dédup front §18.3 — un visiteur
 * ignore l'écho de son propre café). Le format de fil reste celui de la Phase 3
 * (le front 2b/3 lit energy/energyVersion/actionId, le champ `type` est ignoré).
 */
final readonly class EnergyChanged implements DomainEvent
{
    public function __construct(
        public int $energy,
        public int $energyVersion,
        public string $actionId,
    ) {
    }

    public function type(): string
    {
        return 'ENERGY_CHANGED';
    }

    public function payload(): array
    {
        return [
            'energy' => $this->energy,
            'energyVersion' => $this->energyVersion,
            'actionId' => $this->actionId,
        ];
    }
}
