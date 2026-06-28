<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * §24.5 `ADVICE_REACTION_CHANGED` — compteurs de réactions après un changement
 * EFFECTIF (§11.3). `adviceSequence` est le jeton anti-régression DISTINCT
 * (énergie/résultats), pour la barrière front. Compteurs GLOBAUX (invariant B).
 */
final readonly class AdviceReactionChanged implements DomainEvent
{
    public function __construct(
        public int $adviceSequence,
        public int $concerning,
        public int $alreadyDone,
        public int $takingNotes,
    ) {
    }

    public function type(): string
    {
        return 'ADVICE_REACTION_CHANGED';
    }

    public function payload(): array
    {
        return [
            'adviceSequence' => $this->adviceSequence,
            'reactions' => [
                'CONCERNING' => $this->concerning,
                'ALREADY_DONE' => $this->alreadyDone,
                'TAKING_NOTES' => $this->takingNotes,
            ],
        ];
    }
}
