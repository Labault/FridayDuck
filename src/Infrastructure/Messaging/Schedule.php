<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Cycle\CycleStep;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Planning récurrent du cycle d'un vendredi (§25.1). Chaque cron est évalué en
 * heure murale Europe/Paris (DST-aware). `stateful` + `processOnlyLastMissedRun`
 * rattrapent une exécution manquée (Scheduler down) sans rejouer toute la file —
 * et les messages restent idempotents par clé (§25.3).
 */
#[AsSchedule]
final readonly class Schedule implements ScheduleProviderInterface
{
    private \DateTimeZone $dateTimeZone;

    public function __construct(
        private CacheInterface $cache,
        string $businessTimezone,
    ) {
        $this->dateTimeZone = new \DateTimeZone($businessTimezone);
    }

    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->with(
                $this->at('55 23 * * 4', CycleStep::PrepareEdition),     // jeudi 23:55
                $this->at('0 0 * * 5', CycleStep::PublishFridayOpened),  // vendredi 00:00
                $this->at('0 14 * * 5', CycleStep::CloseVote),           // vendredi 14:00
                $this->at('1 14 * * 5', CycleStep::PublishWinner),       // vendredi 14:01
                $this->at('55 23 * * 5', CycleStep::PrepareReport),      // vendredi 23:55
                $this->at('0 0 * * 6', CycleStep::CloseFriday),          // samedi 00:00
                $this->at('5 0 * * 6', CycleStep::GenerateReport),       // samedi 00:05
            );
    }

    private function at(string $cron, CycleStep $cycleStep): RecurringMessage
    {
        return RecurringMessage::cron($cron, new RunCycleStep($cycleStep), $this->dateTimeZone);
    }
}
