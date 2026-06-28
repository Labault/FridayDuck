<?php

declare(strict_types=1);

namespace App\Application\Cycle;

/**
 * Dédup par clé des messages de cycle (§25.3, invariant C).
 *
 * Combiné aux résolveurs DÉJÀ idempotents, il ajoute le dédup au niveau message :
 * gate les ANNONCES de cycle (FridayOpened, gagnant, FridayClosed, bilan) pour
 * qu'elles ne soient émises qu'une seule fois.
 */
interface ProcessedMessageGuard
{
    /**
     * Marque la clé comme traitée. Retourne true si c'est la PREMIÈRE fois (faire
     * l'annonce), false si déjà traitée (l'ignorer). Race-safe.
     */
    public function markIfFirst(string $key): bool;
}
