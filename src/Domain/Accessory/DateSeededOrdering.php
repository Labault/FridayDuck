<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Ordre pseudo-aléatoire DÉTERMINISTE seedé par la date du vendredi (§10.4).
 *
 * Même seed (date du vendredi) + mêmes items → MÊME ordre, à chaque requête et
 * recréation (« le tirage ne doit pas changer à chaque rechargement »). Sert à
 * deux usages :
 *  - sélection des 3 options du jour depuis le catalogue actif (§10.2) ;
 *  - départage du gagnant en cas d'égalité (§10.4, critère 2).
 *
 * PHP pur, sans aléa système : la seule source d'entropie est le seed.
 */
final class DateSeededOrdering
{
    private function __construct()
    {
    }

    /**
     * @template T
     *
     * @param list<T>             $items
     * @param callable(T): string $keyOf identité stable de l'item (ex. code accessoire)
     *
     * @return list<T> items triés par rang pseudo-aléatoire seedé (croissant)
     */
    public static function order(string $seed, array $items, callable $keyOf): array
    {
        usort($items, static fn ($a, $b): int => self::rank($seed, $keyOf($a)) <=> self::rank($seed, $keyOf($b)));

        return $items;
    }

    /**
     * Rang déterministe d'un item pour un seed donné : un hachage stable, comparé
     * lexicographiquement. Distinct par item, identique d'une exécution à l'autre.
     */
    public static function rank(string $seed, string $itemKey): string
    {
        return hash('sha256', $seed.':'.$itemKey);
    }
}
