<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Accessory;

use App\Domain\Accessory\AccessoryWinnerCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessoryWinnerCalculator::class)]
final class AccessoryWinnerCalculatorTest extends TestCase
{
    private const string SEED = '2026-07-03';

    public function testMostVotesWins(): void
    {
        $winner = AccessoryWinnerCalculator::decide(self::SEED, [
            ['code' => 'a', 'voteCount' => 1],
            ['code' => 'b', 'voteCount' => 3],
            ['code' => 'c', 'voteCount' => 2],
        ]);

        self::assertSame('b', $winner);
    }

    public function testTieIsBrokenDeterministicallyAndIndependentlyOfInputOrder(): void
    {
        $tally = [
            ['code' => 'a', 'voteCount' => 0],
            ['code' => 'b', 'voteCount' => 0],
            ['code' => 'c', 'voteCount' => 0],
        ];

        $winner = AccessoryWinnerCalculator::decide(self::SEED, $tally);

        // Même seed, ordre d'entrée inversé → MÊME gagnant (stable au recalcul).
        self::assertSame($winner, AccessoryWinnerCalculator::decide(self::SEED, array_reverse($tally)));
        self::assertContains($winner, ['a', 'b', 'c']);
    }

    public function testTieBreakIsSeededByDate(): void
    {
        $tally = [
            ['code' => 'a', 'voteCount' => 0],
            ['code' => 'b', 'voteCount' => 0],
            ['code' => 'c', 'voteCount' => 0],
        ];

        // Le départage dépend du seed : au moins une date donne un gagnant différent.
        $winners = array_map(
            static fn (string $seed): string => AccessoryWinnerCalculator::decide($seed, $tally),
            ['2026-07-03', '2026-07-10', '2026-07-17', '2026-07-24', '2026-07-31'],
        );

        self::assertGreaterThan(1, \count(array_unique($winners)));
    }
}
