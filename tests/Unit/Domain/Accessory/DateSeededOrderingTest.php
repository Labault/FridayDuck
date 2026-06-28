<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Accessory;

use App\Domain\Accessory\DateSeededOrdering;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateSeededOrdering::class)]
final class DateSeededOrderingTest extends TestCase
{
    /** @var list<string> */
    private const array CATALOGUE = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];

    public function testOrderIsStableForTheSameSeedRegardlessOfInputOrder(): void
    {
        $identity = static fn (string $code): string => $code;

        $ordered = DateSeededOrdering::order('2026-07-03', self::CATALOGUE, $identity);
        $reversed = DateSeededOrdering::order('2026-07-03', array_reverse(self::CATALOGUE), $identity);

        self::assertSame($ordered, $reversed);
    }

    public function testTopThreeSelectionIsStableAcrossCalls(): void
    {
        $identity = static fn (string $code): string => $code;

        $firstThree = \array_slice(DateSeededOrdering::order('2026-07-03', self::CATALOGUE, $identity), 0, 3);
        $againThree = \array_slice(DateSeededOrdering::order('2026-07-03', self::CATALOGUE, $identity), 0, 3);

        self::assertSame($firstThree, $againThree);
        self::assertCount(3, $firstThree);
    }

    public function testDifferentSeedsGenerallyProduceDifferentTopThree(): void
    {
        $identity = static fn (string $code): string => $code;

        $weeks = ['2026-07-03', '2026-07-10', '2026-07-17', '2026-07-24'];
        $selections = array_map(
            static fn (string $seed): array => \array_slice(DateSeededOrdering::order($seed, self::CATALOGUE, $identity), 0, 3),
            $weeks,
        );

        $distinct = array_unique(array_map(static fn (array $three): string => implode(',', $three), $selections));
        self::assertGreaterThan(1, \count($distinct));
    }
}
