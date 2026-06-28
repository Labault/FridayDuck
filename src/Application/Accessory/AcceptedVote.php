<?php

declare(strict_types=1);

namespace App\Application\Accessory;

/**
 * Détail d'un vote accepté, exposé en réponse HTTP (§24.3).
 */
final readonly class AcceptedVote
{
    public function __construct(
        public string $voteId,
        public string $accessoryCode,
        public int $resultsSequence,
    ) {
    }
}
