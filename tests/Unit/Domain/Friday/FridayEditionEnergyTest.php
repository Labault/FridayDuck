<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Friday;

use App\Domain\Friday\FridayEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Formule d'énergie (§8.3/§8.4) : energy = min(100, floor(count/target*100)),
 * surcaféination au plafond, energyVersion monotone.
 */
#[CoversClass(FridayEdition::class)]
final class FridayEditionEnergyTest extends TestCase
{
    public function testProgressionWithThresholdAndOvercaffeination(): void
    {
        // Cible volontairement petite (3) pour observer floor + plafond + surcaf.
        $edition = $this->edition(coffeeTarget: 3);
        self::assertSame(0, $edition->energy());

        $edition->recordCoffee(); // 1/3 → floor(33.3) = 33
        self::assertSame(1, $edition->coffeeCount());
        self::assertSame(33, $edition->energy());
        self::assertSame(0, $edition->overcaffeinationCount());
        self::assertSame(1, $edition->energyVersion());

        $edition->recordCoffee(); // 2/3 → 66
        self::assertSame(66, $edition->energy());

        $edition->recordCoffee(); // 3/3 → 100 (jauge pleine, pas encore de surcaf)
        self::assertSame(100, $edition->energy());
        self::assertSame(0, $edition->overcaffeinationCount());
        self::assertSame(3, $edition->energyVersion());

        $edition->recordCoffee(); // 4e → plafond 100, surcaféination 1
        self::assertSame(100, $edition->energy());
        self::assertSame(1, $edition->overcaffeinationCount());
        self::assertSame(4, $edition->coffeeCount());
        self::assertSame(4, $edition->energyVersion());

        $edition->recordCoffee(); // 5e → surcaféination 2
        self::assertSame(100, $edition->energy());
        self::assertSame(2, $edition->overcaffeinationCount());
    }

    public function testDefaultTargetIsOneEnergyPerCoffeeUntilFull(): void
    {
        $edition = $this->edition(coffeeTarget: 100);

        for ($i = 1; $i <= 100; ++$i) {
            $edition->recordCoffee();
        }
        self::assertSame(100, $edition->energy());
        self::assertSame(100, $edition->coffeeCount());
        self::assertSame(0, $edition->overcaffeinationCount());
        self::assertSame(100, $edition->energyVersion());

        $edition->recordCoffee(); // 101e
        self::assertSame(100, $edition->energy());
        self::assertSame(1, $edition->overcaffeinationCount());
    }

    private function edition(int $coffeeTarget): FridayEdition
    {
        return FridayEdition::open(
            str_pad('ED', 26, '0'),
            new \DateTimeImmutable('2026-07-03'),
            'Europe/Paris',
            $coffeeTarget,
            new \DateTimeImmutable('2026-07-03T10:00:00+02:00'),
        );
    }
}
