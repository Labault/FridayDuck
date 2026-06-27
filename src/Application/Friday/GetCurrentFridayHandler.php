<?php

declare(strict_types=1);

namespace App\Application\Friday;

use App\Application\Visitor\RecordFridayVisit;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Friday\FridayCalendar;

/**
 * Orchestration de la lecture de l'état courant.
 *
 * L'autorité temporelle reste le Domaine de la Phase 1 (FridayCalendar) : c'est
 * lui qui dit « quel vendredi » et AWAKE/DORMANT. La base ne fournit QUE l'état
 * collectif persisté. L'identité du visiteur est toujours résolue ; l'édition
 * n'est résolue-ou-créée (et la visite tracée) QUE le vendredi (§25.2).
 */
final readonly class GetCurrentFridayHandler
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private ResolveCurrentFridayEdition $resolveCurrentFridayEdition,
        private ResolveAnonymousVisitor $resolveAnonymousVisitor,
        private RecordFridayVisit $recordFridayVisit,
    ) {
    }

    public function __invoke(string $visitorHash): CurrentFridayView
    {
        $fridayState = $this->fridayCalendar->currentState();
        $visitorResolution = $this->resolveAnonymousVisitor->resolve($visitorHash);

        $energy = 0;
        $coffeeCount = 0;

        if ($fridayState->active) {
            $edition = $this->resolveCurrentFridayEdition->resolve($fridayState->fridayDate, $fridayState->timezoneName());
            $this->recordFridayVisit->record($edition->id(), $visitorResolution->visitor->id());
            $energy = $edition->energy();
            $coffeeCount = $edition->coffeeCount();
        }

        return new CurrentFridayView(
            active: $fridayState->active,
            date: $fridayState->date(),
            timezone: $fridayState->timezoneName(),
            status: $fridayState->status->value,
            energy: $energy,
            coffeeCount: $coffeeCount,
            visitorIsNew: $visitorResolution->isNew,
        );
    }
}
