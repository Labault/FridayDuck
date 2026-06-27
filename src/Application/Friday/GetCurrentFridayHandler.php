<?php

declare(strict_types=1);

namespace App\Application\Friday;

use App\Domain\Friday\FridayCalendar;

/**
 * Orchestration de la lecture de l'état temporel : interroge le Domaine et
 * projette le résultat dans un modèle de lecture. Aucune règle de calcul ici.
 */
final readonly class GetCurrentFridayHandler
{
    public function __construct(private FridayCalendar $fridayCalendar)
    {
    }

    public function __invoke(): CurrentFridayView
    {
        $fridayState = $this->fridayCalendar->currentState();

        return new CurrentFridayView(
            active: $fridayState->active,
            date: $fridayState->date(),
            timezone: $fridayState->timezoneName(),
            status: $fridayState->status->value,
        );
    }
}
