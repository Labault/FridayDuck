<?php

declare(strict_types=1);

namespace App\Application\Cycle;

/**
 * Clés d'idempotence des messages de cycle (§25.3). Dérivées de la date du
 * vendredi (ou de la semaine ISO pour le bilan) — identiques quel que soit le
 * chemin (Scheduler ou rattrapage), pour le dédup des annonces.
 */
final class CycleKey
{
    private function __construct()
    {
    }

    public static function fridayOpen(\DateTimeImmutable $fridayDate): string
    {
        return 'friday-open:'.$fridayDate->format('Y-m-d');
    }

    public static function accessoryClose(\DateTimeImmutable $fridayDate): string
    {
        return 'accessory-close:'.$fridayDate->format('Y-m-d');
    }

    public static function accessoryWinner(\DateTimeImmutable $fridayDate): string
    {
        return 'accessory-winner:'.$fridayDate->format('Y-m-d');
    }

    public static function fridayClose(\DateTimeImmutable $fridayDate): string
    {
        return 'friday-close:'.$fridayDate->format('Y-m-d');
    }

    public static function weeklyReport(\DateTimeImmutable $fridayDate): string
    {
        // Semaine ISO, ex. weekly-report:2026-W27 (§25.3).
        return 'weekly-report:'.$fridayDate->format('o-\WW');
    }
}
