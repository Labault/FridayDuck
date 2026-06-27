<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * Composition CÔTÉ SERVEUR de la clé d'idempotence café (§8.6) :
 *
 *     coffee:{visitor-hash}:{friday-date}:{client-action-id}
 *
 * Seul `client-action-id` vient du client ; `visitor-hash` et `friday-date`
 * sont dérivés du cookie et de l'horloge — le client ne peut pas usurper une
 * autre identité ni un autre vendredi.
 */
final class CoffeeIdempotencyKey
{
    public static function compose(string $visitorHash, string $fridayDate, string $clientActionId): string
    {
        return \sprintf('coffee:%s:%s:%s', $visitorHash, $fridayDate, $clientActionId);
    }
}
