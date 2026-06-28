<?php

declare(strict_types=1);

namespace App\Application\Coffee;

use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Coffee\CoffeeLimitReached;
use App\Domain\Friday\FridayCalendar;

/**
 * Cas d'usage « offrir un café ».
 *
 * GARDE TEMPORELLE EN PREMIER (invariant A) : NOT_FRIDAY est décidé par le
 * Domaine temporel (horloge), jamais par la colonne status de l'édition. Hors
 * vendredi, aucune mutation. Le vendredi : résout l'identité, garantit l'édition
 * (résoudre-ou-créer), puis sert le café dans la transaction verrouillée — qui
 * écrit aussi l'événement temps réel dans l'outbox (§20.6, atomicité).
 */
final readonly class ServeCoffeeHandler
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private ResolveAnonymousVisitor $resolveAnonymousVisitor,
        private ResolveCurrentFridayEdition $resolveCurrentFridayEdition,
        private ServeCoffee $serveCoffee,
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

        // L'événement ENERGY_CHANGED est désormais écrit dans l'outbox PAR `serve()`,
        // dans la transaction du café et seulement sur acceptation réelle (§20.6,
        // invariant A) ; un relais le publiera. Le handler n'a plus à diffuser.
        return CoffeeOutcome::served($result);
    }
}
