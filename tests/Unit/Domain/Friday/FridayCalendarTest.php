<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Friday;

use App\Domain\Friday\FridayCalendar;
use App\Domain\Friday\FridayStatus;
use App\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FridayCalendar::class)]
final class FridayCalendarTest extends TestCase
{
    private const string BUSINESS_TZ = 'Europe/Paris';

    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function sevenDaysProvider(): iterable
    {
        // Semaine du vendredi 2026-07-03 (heure locale Europe/Paris).
        yield 'lundi' => ['2026-06-29 12:00:00', self::BUSINESS_TZ, false, '2026-07-03'];
        yield 'mardi' => ['2026-06-30 12:00:00', self::BUSINESS_TZ, false, '2026-07-03'];
        yield 'mercredi' => ['2026-07-01 12:00:00', self::BUSINESS_TZ, false, '2026-07-03'];
        yield 'jeudi' => ['2026-07-02 12:00:00', self::BUSINESS_TZ, false, '2026-07-03'];
        yield 'vendredi' => ['2026-07-03 12:00:00', self::BUSINESS_TZ, true, '2026-07-03'];
        yield 'samedi' => ['2026-07-04 12:00:00', self::BUSINESS_TZ, false, '2026-07-10'];
        yield 'dimanche' => ['2026-07-05 12:00:00', self::BUSINESS_TZ, false, '2026-07-10'];
    }

    /**
     * Bornes EXACTES de la bascule, en heure murale Europe/Paris (§7.2).
     *
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function exactBoundariesProvider(): iterable
    {
        yield 'jeudi 23:59:59 → dormant' => ['2026-07-02 23:59:59', self::BUSINESS_TZ, false, '2026-07-03'];
        yield 'vendredi 00:00:00 → awake' => ['2026-07-03 00:00:00', self::BUSINESS_TZ, true, '2026-07-03'];
        yield 'vendredi 23:59:59 → awake' => ['2026-07-03 23:59:59', self::BUSINESS_TZ, true, '2026-07-03'];
        yield 'samedi 00:00:00 → dormant' => ['2026-07-04 00:00:00', self::BUSINESS_TZ, false, '2026-07-10'];
    }

    /**
     * Mêmes bornes mais fournies en UTC : prouve que la bascule se lit sur
     * l'heure murale de Paris, avec le BON décalage (été +02:00 / hiver +01:00).
     *
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function offsetAwareBoundariesProvider(): iterable
    {
        // Été : Paris = UTC+02:00.
        yield 'été — vendredi 23:59:59 Paris' => ['2026-07-03 21:59:59', 'UTC', true, '2026-07-03'];
        yield 'été — samedi 00:00:00 Paris' => ['2026-07-03 22:00:00', 'UTC', false, '2026-07-10'];
        // Hiver : Paris = UTC+01:00.
        yield 'hiver — vendredi 23:59:59 Paris' => ['2026-01-09 22:59:59', 'UTC', true, '2026-01-09'];
        yield 'hiver — samedi 00:00:00 Paris' => ['2026-01-09 23:00:00', 'UTC', false, '2026-01-16'];
    }

    /**
     * Changements d'heure (DST) et de date/année — l'edge case qui casse les
     * applications temporelles.
     *
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function dstAndCalendarEdgesProvider(): iterable
    {
        yield 'vendredi en été (+02:00)' => ['2026-07-03 12:00:00', self::BUSINESS_TZ, true, '2026-07-03'];
        yield 'vendredi en hiver (+01:00)' => ['2026-01-09 12:00:00', self::BUSINESS_TZ, true, '2026-01-09'];
        yield 'vendredi la semaine du passage été' => ['2026-03-27 12:00:00', self::BUSINESS_TZ, true, '2026-03-27'];
        yield 'samedi → prochain vendredi traverse le passage été' => ['2026-03-28 12:00:00', self::BUSINESS_TZ, false, '2026-04-03'];
        yield 'samedi → prochain vendredi traverse le retour hiver' => ['2026-10-24 12:00:00', self::BUSINESS_TZ, false, '2026-10-30'];
        yield 'instant juste avant le saut de printemps (UTC)' => ['2026-03-29 00:30:00', 'UTC', false, '2026-04-03'];
        yield 'instant juste après le saut de printemps (UTC)' => ['2026-03-29 01:30:00', 'UTC', false, '2026-04-03'];
        yield 'jeudi 31/12 → prochain vendredi en 2027 (changement d\'année)' => ['2026-12-31 12:00:00', self::BUSINESS_TZ, false, '2027-01-01'];
    }

    #[DataProvider('sevenDaysProvider')]
    #[DataProvider('exactBoundariesProvider')]
    #[DataProvider('offsetAwareBoundariesProvider')]
    #[DataProvider('dstAndCalendarEdgesProvider')]
    public function testCurrentState(string $instant, string $instantTimezone, bool $expectedActive, string $expectedDate): void
    {
        $state = $this->calendarAt($instant, $instantTimezone)->currentState();

        self::assertSame($expectedActive, $state->active);
        self::assertSame($expectedDate, $state->date());
        self::assertSame(self::BUSINESS_TZ, $state->timezoneName());
        self::assertSame(
            $expectedActive ? FridayStatus::Awake : FridayStatus::Dormant,
            $state->status,
        );
        // Le vendredi de l'édition est TOUJOURS un vendredi.
        self::assertSame('5', $state->fridayDate->format('N'));
    }

    public function testBusinessTimezoneIsConfigurableAndDecisive(): void
    {
        // 2026-07-03 23:30 UTC : samedi à Paris (+02:00), mais encore vendredi en UTC.
        $clock = new FrozenClock(new \DateTimeImmutable('2026-07-03 23:30:00', new \DateTimeZone('UTC')));

        $paris = (new FridayCalendar($clock, 'Europe/Paris'))->currentState();
        self::assertFalse($paris->active);
        self::assertSame('DORMANT', $paris->status->value);

        $utc = (new FridayCalendar($clock, 'UTC'))->currentState();
        self::assertTrue($utc->active);
        self::assertSame('UTC', $utc->timezoneName());
    }

    private function calendarAt(string $instant, string $instantTimezone): FridayCalendar
    {
        $clock = new FrozenClock(new \DateTimeImmutable($instant, new \DateTimeZone($instantTimezone)));

        return new FridayCalendar($clock, self::BUSINESS_TZ);
    }
}
