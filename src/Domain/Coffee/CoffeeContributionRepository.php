<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * Port de persistance des contributions café (§30).
 */
interface CoffeeContributionRepository
{
    public function findByIdempotencyKey(string $idempotencyKey): ?CoffeeContribution;

    public function countForVisitorAndEdition(string $fridayEditionId, string $visitorId): int;

    public function add(CoffeeContribution $coffeeContribution): void;
}
