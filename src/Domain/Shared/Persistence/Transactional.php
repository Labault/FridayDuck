<?php

declare(strict_types=1);

namespace App\Domain\Shared\Persistence;

/**
 * Port d'unité transactionnelle : exécute un travail dans UNE transaction
 * atomique (commit au succès, rollback sur exception).
 *
 * Indispensable à la contribution café (§8 / invariant B) : c'est dans cette
 * transaction qu'on pose le verrou de ligne, vérifie le quota, insère et mute
 * l'énergie — tout ou rien.
 */
interface Transactional
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed;
}
