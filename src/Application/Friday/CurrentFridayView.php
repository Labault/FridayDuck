<?php

declare(strict_types=1);

namespace App\Application\Friday;

use App\Application\Accessory\VoteView;
use App\Application\Advice\AdviceView;

/**
 * Modèle de lecture exposé par l'endpoint (§24.1).
 *
 * `active`/`status` viennent de l'horloge (Phase 1). `energy`/… proviennent de
 * l'édition PERSISTÉE (§8). Le bloc `vote` (§10) n'existe que le vendredi ;
 * `visitorHasVoted` est l'état par-visiteur. Le conseil viendra en Phase 5.
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
        public ?VoteView $vote,
        public bool $visitorHasVoted,
        public ?string $votedAccessoryCode,
        public ?AdviceView $advice,
        public ?string $visitorAdviceReaction,
    ) {
    }
}
