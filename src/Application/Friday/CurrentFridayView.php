<?php

declare(strict_types=1);

namespace App\Application\Friday;

/**
 * Modèle de lecture exposé par l'endpoint (§24.1, sous-ensemble Phase 1).
 *
 * Sous-ensemble réduit : energy / vote / advice / visitor arrivent en Phase 2+.
 */
final readonly class CurrentFridayView
{
    public function __construct(
        public bool $active,
        public string $date,
        public string $timezone,
        public string $status,
    ) {
    }
}
