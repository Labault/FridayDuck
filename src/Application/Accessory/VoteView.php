<?php

declare(strict_types=1);

namespace App\Application\Accessory;

/**
 * Bloc « vote » du GET état courant (§24.1). `open`/`closesAt` sont DÉRIVÉS de
 * l'horloge (§10.1) ; `winner` n'est présent qu'une fois le vote clôturé (§10.6).
 */
final readonly class VoteView
{
    /**
     * @param list<AccessoryOptionView> $options
     */
    public function __construct(
        public bool $open,
        public string $closesAt,
        public ?AccessoryWinnerView $winner,
        public int $resultsSequence,
        public array $options,
    ) {
    }
}
