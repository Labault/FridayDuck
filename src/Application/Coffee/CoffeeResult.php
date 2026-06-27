<?php

declare(strict_types=1);

namespace App\Application\Coffee;

/**
 * Résultat d'une contribution café acceptée OU rejouée (§8.5).
 *
 * Pour un rejeu (`replayed = true`), `previousEnergy === currentEnergy` et aucune
 * mutation n'a eu lieu : on renvoie simplement l'état courant.
 */
final readonly class CoffeeResult
{
    public function __construct(
        public bool $replayed,
        public string $contributionId,
        public int $previousEnergy,
        public int $currentEnergy,
        public int $energyVersion,
        public int $coffeeCount,
        public int $overcaffeinationCount,
        public int $remainingCoffees,
        public bool $reachedThreshold,
    ) {
    }
}
