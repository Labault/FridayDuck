<?php

declare(strict_types=1);

namespace App\Application\Coffee;

use App\Domain\Coffee\CoffeeContribution;
use App\Domain\Coffee\CoffeeContributionRepository;
use App\Domain\Coffee\CoffeeIdempotencyKey;
use App\Domain\Coffee\CoffeeLimitReached;
use App\Domain\Coffee\CoffeeQuota;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\Transactional;
use App\Domain\Visitor\AnonymousVisitor;

/**
 * Cœur de la mécanique café : UNE transaction atomique (invariant B).
 *
 * Ordre : idempotence → verrou d'édition → quota → insert → mutation énergie →
 * commit. Le verrou pessimiste de la ligne édition sérialise à la fois le quota
 * (§8.2) et l'énergie (§8.4) → ni dépassement de quota, ni lost update (D). Un
 * rejeu idempotent (même clé) renvoie l'état courant sans rien re-muter (C).
 *
 * @throws CoffeeLimitReached
 */
final readonly class ServeCoffee
{
    public function __construct(
        private Transactional $transactional,
        private FridayEditionRepository $fridayEditionRepository,
        private CoffeeContributionRepository $coffeeContributionRepository,
        private IdentifierGenerator $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function serve(
        AnonymousVisitor $anonymousVisitor,
        \DateTimeImmutable $fridayDate,
        string $timezone,
        string $clientActionId,
    ): CoffeeResult {
        $key = CoffeeIdempotencyKey::compose(
            $anonymousVisitor->anonymousIdentifierHash(),
            $fridayDate->format('Y-m-d'),
            $clientActionId,
        );

        return $this->transactional->transactional(function () use ($anonymousVisitor, $fridayDate, $timezone, $clientActionId, $key): CoffeeResult {
            // a. Idempotence (chemin rapide, sans verrou) : rejeu → état courant.
            $existing = $this->coffeeContributionRepository->findByIdempotencyKey($key);
            if ($existing instanceof CoffeeContribution) {
                return $this->replay($existing, $this->lockedOrCurrentEdition($fridayDate, $timezone), $anonymousVisitor->id());
            }

            // b. Verrou d'écriture : sérialise quota ET énergie pour cette édition.
            $edition = $this->fridayEditionRepository->findByFridayForUpdate($fridayDate, $timezone);
            if (!$edition instanceof FridayEdition) {
                throw new \RuntimeException('Édition introuvable au moment de servir le café.');
            }

            // Re-vérifie l'idempotence SOUS le verrou (course même-clé sérialisée).
            $existing = $this->coffeeContributionRepository->findByIdempotencyKey($key);
            if ($existing instanceof CoffeeContribution) {
                return $this->replay($existing, $edition, $anonymousVisitor->id());
            }

            // c. Quota (§8.2) : compté sous le verrou → jamais de dépassement.
            $count = $this->coffeeContributionRepository->countForVisitorAndEdition($edition->id(), $anonymousVisitor->id());
            if ($count >= CoffeeQuota::MAX_PER_VISITOR) {
                throw new CoffeeLimitReached('Quota de café atteint pour ce visiteur.');
            }

            // d/e. Insert + recalcul d'énergie (une seule fois pour ce café).
            $previousEnergy = $edition->energy();
            $edition->recordCoffee();
            $coffeeContribution = CoffeeContribution::record(
                $this->identifierGenerator->nextIdentifier(),
                $edition->id(),
                $anonymousVisitor->id(),
                $key,
                $clientActionId,
                $previousEnergy,
                $edition->energy(),
                $this->clock->now(),
            );
            $this->coffeeContributionRepository->add($coffeeContribution);
            $this->fridayEditionRepository->save($edition);

            // f. commit (assuré par le wrapper transactionnel).
            return new CoffeeResult(
                replayed: false,
                contributionId: $coffeeContribution->id(),
                previousEnergy: $previousEnergy,
                currentEnergy: $edition->energy(),
                energyVersion: $edition->energyVersion(),
                coffeeCount: $edition->coffeeCount(),
                overcaffeinationCount: $edition->overcaffeinationCount(),
                remainingCoffees: max(0, CoffeeQuota::MAX_PER_VISITOR - ($count + 1)),
                reachedThreshold: $previousEnergy < 100 && $edition->energy() >= 100,
            );
        });
    }

    private function lockedOrCurrentEdition(\DateTimeImmutable $fridayDate, string $timezone): FridayEdition
    {
        $edition = $this->fridayEditionRepository->findByFriday($fridayDate, $timezone);
        if (!$edition instanceof FridayEdition) {
            throw new \RuntimeException('Édition introuvable pour le rejeu de café.');
        }

        return $edition;
    }

    private function replay(CoffeeContribution $coffeeContribution, FridayEdition $fridayEdition, string $visitorId): CoffeeResult
    {
        $count = $this->coffeeContributionRepository->countForVisitorAndEdition($fridayEdition->id(), $visitorId);

        return new CoffeeResult(
            replayed: true,
            contributionId: $coffeeContribution->id(),
            previousEnergy: $fridayEdition->energy(),
            currentEnergy: $fridayEdition->energy(),
            energyVersion: $fridayEdition->energyVersion(),
            coffeeCount: $fridayEdition->coffeeCount(),
            overcaffeinationCount: $fridayEdition->overcaffeinationCount(),
            remainingCoffees: max(0, CoffeeQuota::MAX_PER_VISITOR - $count),
            reachedThreshold: false,
        );
    }
}
