<?php

declare(strict_types=1);

namespace App\Application\Coffee;

use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\RealTime\DomainEventPublisher;
use App\Application\RealTime\EnergyChanged;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Coffee\CoffeeLimitReached;
use App\Domain\Friday\FridayCalendar;

/**
 * Cas d'usage « offrir un café ».
 *
 * GARDE TEMPORELLE EN PREMIER (invariant A) : NOT_FRIDAY est décidé par le
 * Domaine temporel (horloge), jamais par la colonne status de l'édition. Hors
 * vendredi, aucune mutation. Le vendredi : résout l'identité, garantit l'édition
 * (résoudre-ou-créer), puis sert le café dans la transaction verrouillée.
 */
final readonly class ServeCoffeeHandler
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private ResolveAnonymousVisitor $resolveAnonymousVisitor,
        private ResolveCurrentFridayEdition $resolveCurrentFridayEdition,
        private ServeCoffee $serveCoffee,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function handle(string $visitorHash, string $clientActionId): CoffeeOutcome
    {
        $fridayState = $this->fridayCalendar->currentState();
        if (!$fridayState->active) {
            return CoffeeOutcome::notFriday();
        }

        $visitor = $this->resolveAnonymousVisitor->resolve($visitorHash)->visitor;
        // Garantit l'existence de l'édition (race-safe) avant de la verrouiller.
        $this->resolveCurrentFridayEdition->resolve($fridayState->fridayDate, $fridayState->timezoneName());

        try {
            $result = $this->serveCoffee->serve(
                $visitor,
                $fridayState->fridayDate,
                $fridayState->timezoneName(),
                $clientActionId,
            );
        } catch (CoffeeLimitReached) {
            return CoffeeOutcome::limitReached();
        }

        // Publication POST-COMMIT (invariant A) : `serve()` a déjà committé en
        // revenant. On ne diffuse que sur une acceptation RÉELLE — jamais sur un
        // rejeu idempotent (sinon double-comptage), jamais sur NOT_FRIDAY/quota.
        // Le port est best-effort : un échec n'annule pas le café (§20.6).
        if (!$result->replayed) {
            $this->domainEventPublisher->publish(
                $fridayState->fridayDate,
                new EnergyChanged($result->currentEnergy, $result->energyVersion, $clientActionId),
            );
        }

        return CoffeeOutcome::served($result);
    }
}
