<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * Topic Mercure PUBLIC d'une édition (§20.2).
 *
 * IRI stable dérivé de la date du vendredi — identique des deux côtés : la
 * publication (serveur) et l'abonnement (navigateur) le calculent depuis la même
 * source, sans résolution divergente.
 */
final class FridayTopic
{
    private const string BASE = 'https://duck-friday.example/fridays/';

    private function __construct()
    {
    }

    public static function forDate(\DateTimeImmutable $fridayDate): string
    {
        return self::forDateString($fridayDate->format('Y-m-d'));
    }

    public static function forDateString(string $fridayDate): string
    {
        return self::BASE.$fridayDate;
    }
}
