<?php

declare(strict_types=1);

namespace App\Domain\Friday;

/**
 * Statut temporel du Canard (§7.2).
 *
 * - AWAKE   : le vendredi, de 00:00:00 (inclus) au samedi 00:00:00 (exclu).
 * - DORMANT : tout le reste de la semaine.
 */
enum FridayStatus: string
{
    case Awake = 'AWAKE';
    case Dormant = 'DORMANT';
}
