<?php

declare(strict_types=1);

namespace App\Application\Accessory;

/**
 * Projection du gagnant (§10.5/§10.6) : de quoi le MONTER (slot, svgGroupId) ET
 * l'ÉTIQUETER (label, alternative textuelle §28.3) côté front. Exposée par le GET,
 * l'événement ACCESSORY_WINNER_SELECTED et la réponse VOTE_CLOSED.
 */
final readonly class AccessoryWinnerView
{
    public function __construct(
        public string $code,
        public string $label,
        public string $slot,
        public string $svgGroupId,
    ) {
    }
}
