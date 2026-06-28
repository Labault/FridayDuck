<?php

declare(strict_types=1);

namespace App\Application\RealTime;

use App\Application\Telemetry\Tracer;
use App\Domain\Outbox\OutboxEntry;
use App\Domain\Outbox\OutboxEntryRepository;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Persistence\Transactional;

/**
 * Relais de l'outbox (§20.6, invariants B/C/E).
 *
 * Pour chaque édition ayant des lignes non publiées, sous VERROU race-safe
 * (FOR UPDATE SKIP LOCKED) : publie chaque ligne EN ORDRE (`id` croissant) sur le
 * transport temps réel, puis marque `publishedAt` — jamais deux fois (invariant B).
 * Un crash après publication mais avant marquage → re-publication au prochain
 * passage (at-least-once, absorbée par la barrière/les clés côté front).
 *
 * À la PREMIÈRE publication en échec d'une édition, on s'arrête (ordre préservé) :
 * les succès et la tentative échouée sont committés, puis {@see OutboxRelayFailed}
 * déclenche le rejeu Messenger (→ file d'échec après seuil).
 */
final readonly class OutboxRelay
{
    public function __construct(
        private OutboxEntryRepository $outboxEntryRepository,
        private RealTimeTransport $realTimeTransport,
        private Transactional $transactional,
        private RelayMetrics $relayMetrics,
        private ClockInterface $clock,
        private Tracer $tracer,
    ) {
    }

    public function relayPending(): void
    {
        $this->relayMetrics->backlogDepth($this->outboxEntryRepository->countPending());

        $hadFailure = false;
        foreach ($this->outboxEntryRepository->pendingFridayDates() as $fridayDate) {
            if (!$this->relayEdition($fridayDate)) {
                $hadFailure = true;
            }
        }

        if ($hadFailure) {
            throw new OutboxRelayFailed('Au moins un événement de l’outbox n’a pu être publié ; rejeu programmé.');
        }
    }

    /**
     * @return bool true si toutes les lignes de l'édition ont été publiées
     */
    private function relayEdition(string $fridayDate): bool
    {
        $topic = FridayTopic::forDateString($fridayDate);

        return $this->transactional->transactional(function () use ($fridayDate, $topic): bool {
            foreach ($this->outboxEntryRepository->lockPendingForEdition($fridayDate) as $outboxEntry) {
                if (!$this->publishOne($topic, $outboxEntry)) {
                    return false; // on s'arrête sur cette édition → ordre préservé
                }
            }

            return true;
        });
    }

    private function publishOne(string $topic, OutboxEntry $outboxEntry): bool
    {
        try {
            // Span ENFANT de la trace de la requête d'origine (traceparent restauré) :
            // le clic café est traçable bout-en-bout par-dessus la frontière async (§26.2).
            $this->tracer->traceLinkedTo(
                'mercure.update.publish',
                $outboxEntry->traceparent(),
                ['friday.date' => $outboxEntry->fridayDate(), 'event.type' => $outboxEntry->type()],
                fn () => $this->realTimeTransport->publish($topic, $outboxEntry->payload()),
            );
        } catch (\Throwable) {
            $this->relayMetrics->publishFailed();
            $outboxEntry->recordFailedAttempt();
            $this->outboxEntryRepository->save($outboxEntry);

            return false;
        }

        $this->relayMetrics->publishSucceeded();
        $outboxEntry->markPublished($this->clock->now());
        $this->outboxEntryRepository->save($outboxEntry);

        return true;
    }
}
