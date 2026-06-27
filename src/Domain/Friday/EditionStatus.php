<?php

declare(strict_types=1);

namespace App\Domain\Friday;

/**
 * Cycle de vie PERSISTÉ de l'édition (§23.1) — géré pleinement par le Scheduler
 * en Phase 6. À NE PAS confondre avec FridayStatus (AWAKE/DORMANT), qui reste
 * calculé depuis l'horloge (§7.2) et seul exposé par l'endpoint.
 *
 * Au résoudre-ou-créer à la volée, une édition naît OPEN.
 */
enum EditionStatus: string
{
    case Open = 'OPEN';
    case Closed = 'CLOSED';
}
