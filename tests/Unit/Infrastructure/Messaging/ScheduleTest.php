<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\Schedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * Le planning du cycle (§25.1) est évalué en heure MURALE Europe/Paris : chaque
 * cron tombe au bon instant local quel que soit le changement d'heure (DST).
 */
#[CoversClass(Schedule::class)]
final class ScheduleTest extends TestCase
{
    private const string TZ = 'Europe/Paris';

    public function testRegistersTheSevenCycleStepsAndTheOutboxRelay(): void
    {
        // 7 étapes de cycle (§25.1) + relais d'outbox (6b) + tick de diagnostic (7c).
        self::assertCount(9, $this->schedule()->getSchedule()->getRecurringMessages());

        foreach (['55 23 * * 4', '0 0 * * 5', '0 14 * * 5', '1 14 * * 5', '55 23 * * 5', '0 0 * * 6', '5 0 * * 6'] as $cron) {
            $this->triggerFor($cron); // échoue si le cron de cycle manque
        }
    }

    /**
     * Bascule d'automne 2026 : le dernier dimanche d'octobre est le 25, donc le
     * samedi 24 (00:00 CEST, +0200) précède la bascule et le samedi 31 (00:00 CET,
     * +0100) la suit. La tâche « samedi 00:00 » reste à minuit MURAL dans les deux
     * cas — preuve que le cron est DST-aware (et non figé sur un offset UTC).
     */
    public function testSaturdayMidnightStaysWallClockAcrossAutumnDstSwitch(): void
    {
        $saturdayMidnight = $this->triggerFor('0 0 * * 6');

        $beforeSwitch = $saturdayMidnight->getNextRunDate(
            new \DateTimeImmutable('2026-10-23T12:00:00', new \DateTimeZone(self::TZ)),
        );
        $afterSwitch = $saturdayMidnight->getNextRunDate(
            new \DateTimeImmutable('2026-10-24T12:00:00', new \DateTimeZone(self::TZ)),
        );

        self::assertNotNull($beforeSwitch);
        self::assertNotNull($afterSwitch);

        // Même heure murale (minuit) de part et d'autre de la bascule…
        self::assertSame('2026-10-24 00:00', $beforeSwitch->setTimezone(new \DateTimeZone(self::TZ))->format('Y-m-d H:i'));
        self::assertSame('2026-10-31 00:00', $afterSwitch->setTimezone(new \DateTimeZone(self::TZ))->format('Y-m-d H:i'));
        // …mais offsets UTC différents : +02:00 (CEST) puis +01:00 (CET).
        self::assertSame('+02:00', $beforeSwitch->setTimezone(new \DateTimeZone(self::TZ))->format('P'));
        self::assertSame('+01:00', $afterSwitch->setTimezone(new \DateTimeZone(self::TZ))->format('P'));
    }

    /**
     * Bascule de printemps 2026 : le dernier dimanche de mars est le 29 ; la tâche
     * « vendredi 14:00 » du vendredi 27 reste à 14:00 mural malgré la nuit courte
     * qui suit.
     */
    public function testFridayAfternoonStaysWallClockAroundSpringDstSwitch(): void
    {
        $closeVote = $this->triggerFor('0 14 * * 5');

        $run = $closeVote->getNextRunDate(
            new \DateTimeImmutable('2026-03-27T06:00:00', new \DateTimeZone(self::TZ)),
        );

        self::assertNotNull($run);
        self::assertSame('2026-03-27 14:00', $run->setTimezone(new \DateTimeZone(self::TZ))->format('Y-m-d H:i'));
        self::assertSame('+01:00', $run->setTimezone(new \DateTimeZone(self::TZ))->format('P'));
    }

    private function triggerFor(string $cron): TriggerInterface
    {
        foreach ($this->schedule()->getSchedule()->getRecurringMessages() as $recurringMessage) {
            $trigger = $recurringMessage->getTrigger();
            if ((string) $trigger === $cron) {
                return $trigger;
            }
        }

        self::fail(\sprintf('Aucun déclencheur pour le cron « %s ».', $cron));
    }

    private function schedule(): Schedule
    {
        return new Schedule(new ArrayAdapter(), self::TZ);
    }
}
