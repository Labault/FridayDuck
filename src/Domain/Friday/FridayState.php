<?php

declare(strict_types=1);

namespace App\Domain\Friday;

/**
 * État temporel courant, calculé (jamais persisté en Phase 1).
 *
 * Objet-valeur pur. `fridayDate` est le vendredi de l'édition courante ou à
 * venir (minuit, fuseau métier) ; `active`/`status` disent si l'on est
 * actuellement vendredi.
 */
final readonly class FridayState
{
    public function __construct(
        public bool $active,
        public \DateTimeImmutable $fridayDate,
        public FridayStatus $status,
        public \DateTimeZone $timezone,
    ) {
    }

    /**
     * Date du vendredi au format ISO (Y-m-d), dans le fuseau métier.
     */
    public function date(): string
    {
        return $this->fridayDate->format('Y-m-d');
    }

    public function timezoneName(): string
    {
        return $this->timezone->getName();
    }
}
