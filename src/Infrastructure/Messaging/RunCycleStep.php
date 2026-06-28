<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Cycle\CycleStep;

/**
 * Message déclenché par le Scheduler (§25.1) pour exécuter une étape du cycle.
 * Ne porte PAS de date : le handler la calcule depuis l'horloge à l'exécution
 * (le vendredi courant, ou le vendredi écoulé pour les tâches du samedi).
 */
final readonly class RunCycleStep
{
    public function __construct(public CycleStep $step)
    {
    }
}
