<?php

declare(strict_types=1);

namespace App\Application\Friday;

/**
 * Modèle de lecture exposé par l'endpoint (§24.1, sous-ensemble Phase 2a-i).
 *
 * `active`/`status` viennent de l'horloge (Phase 1). `energy`/`coffeeCount`
 * proviennent de l'édition PERSISTÉE (0/0 tant que le café n'existe pas —
 * Phase 2a-ii). Le bloc visiteur est minimal : l'identité (nouveau ou non). Le
 * reste (remainingCoffees, vote, advice…) se remplira aux phases suivantes.
 */
final readonly class CurrentFridayView
{
    public function __construct(
        public bool $active,
        public string $date,
        public string $timezone,
        public string $status,
        public int $energy,
        public int $coffeeCount,
        public bool $visitorIsNew,
    ) {
    }
}
