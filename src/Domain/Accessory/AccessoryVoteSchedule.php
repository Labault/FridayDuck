<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

use App\Domain\Shared\Clock\ClockInterface;

/**
 * Fenêtre de vote DÉRIVÉE DE L'HORLOGE (§10.1, invariant A) — PHP pur.
 *
 * Le vote ferme à 14:00 heure murale d'Europe/Paris (DST-aware). On ne lit JAMAIS
 * une colonne de statut : « ouvert ? » = `now < 14:00` du vendredi. Après 14:00,
 * le vote est fermé même si le Scheduler (Phase 6) n'a rien fait (§25.2).
 */
final readonly class AccessoryVoteSchedule
{
    private const string CLOSING_WALL_TIME = '14:00:00';

    private \DateTimeZone $dateTimeZone;

    public function __construct(
        private ClockInterface $clock,
        string $businessTimezone,
    ) {
        $this->dateTimeZone = new \DateTimeZone($businessTimezone);
    }

    /**
     * Instant de clôture : 14:00 heure murale Paris du vendredi (§10.1).
     */
    public function closesAt(\DateTimeImmutable $fridayDate): \DateTimeImmutable
    {
        $day = $fridayDate->setTimezone($this->dateTimeZone)->format('Y-m-d');

        return new \DateTimeImmutable($day.' '.self::CLOSING_WALL_TIME, $this->dateTimeZone);
    }

    /**
     * Le vote est-il ouvert MAINTENANT ? Strictement avant 14:00 (à 14:00:00 pile
     * il est fermé).
     */
    public function isOpen(\DateTimeImmutable $fridayDate): bool
    {
        return $this->clock->now() < $this->closesAt($fridayDate);
    }
}
