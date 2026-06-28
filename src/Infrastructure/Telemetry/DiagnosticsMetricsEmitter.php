<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use App\Application\Telemetry\Metrics;
use App\Domain\Friday\EditionStatus;
use App\Domain\Friday\FridayCalendar;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Outbox\OutboxEntryRepository;
use App\Domain\Shared\Clock\ClockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Émetteur de JAUGES de diagnostic (Phase 7c) — le minimum d'instrumentation pour
 * rendre les alertes §26.7 alertables sur des métriques RÉELLES (danger Y : jamais
 * d'alerte vers une métrique jamais émise). Périodique (Scheduler), HORS chemin
 * métier. Lit l'HORLOGE et l'état persisté pour exposer notamment la DIVERGENCE
 * horloge/EditionStatus — un SIGNAL, jamais une correction (invariant B de 6a).
 *
 * Toutes les valeurs sont des jauges (état courant), rafraîchies à chaque tick :
 * une absence prolongée de ces jauges EST le dead-man switch (danger Y).
 */
final readonly class DiagnosticsMetricsEmitter
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private FridayEditionRepository $fridayEditionRepository,
        private OutboxEntryRepository $outboxEntryRepository,
        private ManagerRegistry $managerRegistry,
        private Metrics $metrics,
        private ClockInterface $clock,
        private string $businessTimezone,
        private string $appFakeNow,
        private string $environment,
    ) {
    }

    public function emit(): void
    {
        $fridayState = $this->fridayCalendar->currentState();
        $isFridayNow = $fridayState->active;
        $edition = $this->fridayEditionRepository->findByFriday($this->fridayCalendar->mostRecentFriday(), $this->businessTimezone);
        $editionOpen = $edition instanceof FridayEdition && EditionStatus::Open === $edition->status();

        $this->metrics->gauge('duck.clock.friday_active', $isFridayNow ? 1 : 0);
        $this->metrics->gauge('duck.edition.open', $editionOpen ? 1 : 0);

        // DIVERGENCE (inv. B) : vendredi ⇒ devrait être OPEN ; sinon ⇒ CLOSED. Si
        // une édition existe et contredit l'horloge → 1 (SIGNAL, pas correction).
        $divergence = $edition instanceof FridayEdition && $isFridayNow !== $editionOpen;
        $this->metrics->gauge('duck.clock.status_divergence', $divergence ? 1 : 0);

        $this->metrics->gauge('duck.vote.unresolved_after_close', $this->voteUnresolvedAfterClose($isFridayNow, $edition) ? 1 : 0);
        $this->metrics->gauge('mercure.outbox.backlog', $this->outboxEntryRepository->countPending());
        $this->metrics->gauge('messenger.failed_messages', $this->failedMessageCount());

        // APP_FAKE_NOW NE DOIT JAMAIS être présent en prod (§7.4) → jauge surveillée.
        $this->metrics->gauge('duck.app_fake_now_active', '' !== trim($this->appFakeNow) ? 1 : 0, ['environment' => '' !== $this->environment ? $this->environment : 'unknown']);
    }

    private function voteUnresolvedAfterClose(bool $isFridayNow, ?FridayEdition $fridayEdition): bool
    {
        if (!$isFridayNow || !$fridayEdition instanceof FridayEdition || null !== $fridayEdition->winnerAccessoryCode()) {
            return false;
        }

        $dateTimeZone = new \DateTimeZone($this->businessTimezone);
        $businessNow = $this->clock->now()->setTimezone($dateTimeZone);
        $closesAt = new \DateTimeImmutable($businessNow->format('Y-m-d').' 14:00:00', $dateTimeZone);

        return $businessNow >= $closesAt;
    }

    private function failedMessageCount(): int
    {
        try {
            $connection = $this->managerRegistry->getConnection();
            \assert($connection instanceof Connection);

            $count = $connection->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'");

            return is_numeric($count) ? (int) $count : 0;
        } catch (\Throwable) {
            // Table absente (transport non Doctrine, ou non encore créée) → neutre.
            return 0;
        }
    }
}
