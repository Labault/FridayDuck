<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Désignation DÉTERMINISTE du gagnant (§10.4) — PHP pur.
 *
 * Ordre de départage :
 *  1. nombre de votes (décroissant) ;
 *  2. en cas d'égalité, ordre pseudo-aléatoire seedé par la date du vendredi
 *     ({@see DateSeededOrdering}) — stable d'un recalcul à l'autre.
 *
 * Même tally + même seed → MÊME gagnant : la résolution paresseuse peut être
 * rejouée sans risque (idempotence du calcul, invariant C).
 */
final class AccessoryWinnerCalculator
{
    private function __construct()
    {
    }

    /**
     * @param non-empty-list<array{code: string, voteCount: int}> $tally
     *
     * @return string code de l'accessoire gagnant
     */
    public static function decide(string $seed, array $tally): string
    {
        usort($tally, static function (array $a, array $b) use ($seed): int {
            if ($a['voteCount'] !== $b['voteCount']) {
                return $b['voteCount'] <=> $a['voteCount'];
            }

            return DateSeededOrdering::rank($seed, $a['code']) <=> DateSeededOrdering::rank($seed, $b['code']);
        });

        return $tally[0]['code'];
    }
}
