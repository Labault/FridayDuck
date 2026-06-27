<?php

declare(strict_types=1);

namespace App\Application\Friday;

/**
 * Modèle de lecture exposé par l'endpoint (§24.1, sous-ensemble Phase 2a-ii).
 *
 * `active`/`status` viennent de l'horloge (Phase 1). `energy`/`energyVersion`/
 * `coffeeCount`/`overcaffeinationCount` proviennent de l'édition PERSISTÉE et
 * reflètent les cafés servis (§8). Le bloc visiteur expose l'identité et le
 * quota restant. Vote/conseil viendront aux phases suivantes.
 */
final readonly class CurrentFridayView
{
    public function __construct(
        public bool $active,
        public string $date,
        public string $timezone,
        public string $status,
        public int $energy,
        public int $energyVersion,
        public int $coffeeCount,
        public int $overcaffeinationCount,
        public bool $visitorIsNew,
        public int $remainingCoffees,
    ) {
    }
}
