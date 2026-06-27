<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Friday;

use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Teste la branche de RATTRAPAGE de course sans base : findByFriday renvoie
 * d'abord null (rien), puis le gagnant ; add() lève ConcurrentCreationException.
 * Le service doit relire et renvoyer le gagnant.
 */
#[CoversClass(ResolveCurrentFridayEdition::class)]
final class ResolveCurrentFridayEditionRetryTest extends TestCase
{
    public function testReReadsWinnerWhenInsertLosesTheRace(): void
    {
        $date = new \DateTimeImmutable('2026-07-03', new \DateTimeZone('Europe/Paris'));
        $now = new \DateTimeImmutable('2026-07-03T10:00:00+02:00');
        $winnerId = str_pad('WINNER', 26, '0');
        $winner = FridayEdition::open($winnerId, $date, 'Europe/Paris', 100, $now);

        $repository = new class($winner) implements FridayEditionRepository {
            private int $finds = 0;

            public function __construct(private readonly FridayEdition $winner)
            {
            }

            public function findByFriday(\DateTimeImmutable $fridayDate, string $timezone): ?FridayEdition
            {
                // Première lecture : rien. Après la course perdue : le gagnant.
                return 0 === $this->finds++ ? null : $this->winner;
            }

            public function add(FridayEdition $edition): void
            {
                throw new ConcurrentCreationException('race');
            }
        };

        $service = new ResolveCurrentFridayEdition(
            $repository,
            new UlidIdentifierGenerator(),
            new FrozenClock($now),
            100,
        );

        $result = $service->resolve($date, 'Europe/Paris');

        self::assertSame($winnerId, $result->id());
    }
}
