<?php

declare(strict_types=1);

namespace App\Application\Advice;

use App\Domain\Advice\AdviceReactionType;

/**
 * Résultat d'un PUT réaction. `changed` distingue un changement EFFECTIF (insert
 * ou swap) d'un no-op idempotent (même réaction) — seul un changement effectif
 * publie ADVICE_REACTION_CHANGED (invariant E). Porte les compteurs courants.
 */
final readonly class AdviceReactionResult
{
    public function __construct(
        public bool $changed,
        public AdviceReactionType $reaction,
        public int $adviceSequence,
        public int $concerning,
        public int $alreadyDone,
        public int $takingNotes,
    ) {
    }
}
