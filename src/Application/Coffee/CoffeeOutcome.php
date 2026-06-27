<?php

declare(strict_types=1);

namespace App\Application\Coffee;

/**
 * Issue d'une tentative de café, projetée en HTTP par la Présentation (§24.2).
 * Le rejeu idempotent est un SUCCÈS (Served avec replayed = true), pas une erreur.
 */
final readonly class CoffeeOutcome
{
    private function __construct(
        public CoffeeOutcomeStatus $status,
        public ?CoffeeResult $result,
    ) {
    }

    public static function notFriday(): self
    {
        return new self(CoffeeOutcomeStatus::NotFriday, null);
    }

    public static function limitReached(): self
    {
        return new self(CoffeeOutcomeStatus::LimitReached, null);
    }

    public static function served(CoffeeResult $coffeeResult): self
    {
        return new self(CoffeeOutcomeStatus::Served, $coffeeResult);
    }
}
