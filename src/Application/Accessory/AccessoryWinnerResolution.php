<?php

declare(strict_types=1);

namespace App\Application\Accessory;

/**
 * Résultat d'une résolution de gagnant. `justResolved` distingue le PREMIER
 * calcul (qui doit publier ACCESSORY_WINNER_SELECTED) d'une relecture idempotente
 * du gagnant déjà figé (§10.1).
 */
final readonly class AccessoryWinnerResolution
{
    public function __construct(
        public string $winnerCode,
        public bool $justResolved,
    ) {
    }
}
