<?php

declare(strict_types=1);

namespace App\Application\Accessory;

/**
 * Résultat d'un vote accepté : identifiant du vote et nouvelle séquence de
 * résultats (jeton anti-régression diffusé en ACCESSORY_RESULTS_UPDATED, §24.5).
 */
final readonly class AccessoryVoteResult
{
    public function __construct(
        public string $voteId,
        public int $resultsSequence,
    ) {
    }
}
