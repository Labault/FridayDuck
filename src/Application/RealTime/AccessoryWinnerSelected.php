<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * §24.5 `ACCESSORY_WINNER_SELECTED` — gagnant figé après 14:00. Événement
 * TERMINAL (pas de séquence). Porte de quoi MONTER et ÉTIQUETER le gagnant côté
 * front (§10.5, §28.3) : code, label, slot et groupe SVG porteur.
 */
final readonly class AccessoryWinnerSelected implements DomainEvent
{
    public function __construct(
        public string $code,
        public string $label,
        public string $slot,
        public string $svgGroupId,
    ) {
    }

    public function type(): string
    {
        return 'ACCESSORY_WINNER_SELECTED';
    }

    public function payload(): array
    {
        return [
            'winner' => [
                'code' => $this->code,
                'label' => $this->label,
                'slot' => $this->slot,
                'svgGroupId' => $this->svgGroupId,
            ],
        ];
    }
}
