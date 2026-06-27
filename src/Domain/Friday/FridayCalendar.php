<?php

declare(strict_types=1);

namespace App\Domain\Friday;

use App\Domain\Shared\Clock\ClockInterface;

/**
 * Cœur temporel du Canard (§7.2) — PHP pur, zéro dépendance technique.
 *
 * À partir de `clock->now()` ramené au fuseau métier, répond : est-ce vendredi,
 * quelle est la date du vendredi de l'édition, et quel statut (AWAKE/DORMANT).
 *
 * Le fuseau métier (Europe/Paris) est injecté en UN seul point (paramètre
 * `app.business_timezone`, voir config/services.yaml) : il n'est jamais codé en
 * dur dans la logique.
 *
 * Convention « édition courante » : le vendredi retourné est celui d'AUJOURD'HUI
 * si l'on est vendredi, sinon le PROCHAIN vendredi — l'édition vers laquelle on
 * se dirige (utile pour afficher « de retour vendredi <date> » à l'état dormant).
 * Cette résolution est volontairement centralisée ici pour que la Phase 2
 * (persistance des éditions) puisse l'affiner sans la disperser.
 */
final readonly class FridayCalendar
{
    private const int FRIDAY = 5; // ISO-8601 : lundi = 1 … dimanche = 7.

    private \DateTimeZone $dateTimeZone;

    public function __construct(
        private ClockInterface $clock,
        string $businessTimezone,
    ) {
        $this->dateTimeZone = new \DateTimeZone($businessTimezone);
    }

    public function currentState(): FridayState
    {
        $now = $this->businessNow();
        $isFriday = self::FRIDAY === (int) $now->format('N');

        return new FridayState(
            $isFriday,
            $this->currentOrNextFriday($now),
            $isFriday ? FridayStatus::Awake : FridayStatus::Dormant,
            $this->dateTimeZone,
        );
    }

    /**
     * Instant courant ramené au fuseau métier — la bascule vendredi/samedi se lit
     * toujours sur l'heure murale d'Europe/Paris, pas sur l'instant UTC.
     */
    private function businessNow(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->dateTimeZone);
    }

    private function currentOrNextFriday(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $dayOfWeek = (int) $now->format('N');
        $daysUntilFriday = (self::FRIDAY - $dayOfWeek + 7) % 7; // 0 si déjà vendredi.

        // `modify('+N days')` est une opération CALENDAIRE : elle préserve minuit
        // heure murale même si l'intervalle traverse un changement d'heure (DST).
        return $now
            ->setTime(0, 0)
            ->modify(\sprintf('+%d days', $daysUntilFriday));
    }
}
