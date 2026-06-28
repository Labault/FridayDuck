<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * §24.5 `ACCESSORY_RESULTS_UPDATED` — résultats de vote après un vote accepté.
 *
 * `resultsSequence` est une séquence DISTINCTE d'`energyVersion`, bumpée à chaque
 * vote : le jeton anti-régression que le front 4b utilise pour ignorer des
 * résultats périmés. Compteurs GLOBAUX uniquement (invariant B).
 */
final readonly class AccessoryResultsUpdated implements DomainEvent
{
    /**
     * @param list<array{code: string, displayOrder: int, voteCount: int}> $options
     */
    public function __construct(
        public int $resultsSequence,
        public array $options,
    ) {
    }

    public function type(): string
    {
        return 'ACCESSORY_RESULTS_UPDATED';
    }

    public function payload(): array
    {
        return [
            'resultsSequence' => $this->resultsSequence,
            'options' => $this->options,
        ];
    }
}
