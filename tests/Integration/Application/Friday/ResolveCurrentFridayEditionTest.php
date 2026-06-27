<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Friday;

use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Domain\Friday\FridayEdition;
use App\Domain\Shared\Persistence\ConcurrentCreationException;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ResolveCurrentFridayEdition::class)]
#[CoversClass(DoctrineFridayEditionRepository::class)]
final class ResolveCurrentFridayEditionTest extends DatabaseTestCase
{
    public function testResolveOrCreateIsIdempotent(): void
    {
        $resolve = $this->resolver();
        $date = new \DateTimeImmutable('2026-07-03', new \DateTimeZone('Europe/Paris'));

        $first = $resolve->resolve($date, 'Europe/Paris');
        $second = $resolve->resolve($date, 'Europe/Paris');

        self::assertSame($first->id(), $second->id());
        self::assertSame(1, $this->countRows('friday_edition'));
        self::assertSame(0, $first->energy());
        self::assertSame(0, $first->coffeeCount());
        self::assertSame(100, $first->coffeeTarget());
    }

    public function testConcurrentInsertViolatesUniqueAndKeepsSingleRow(): void
    {
        $repository = new DoctrineFridayEditionRepository($this->registry);
        $date = new \DateTimeImmutable('2026-07-03', new \DateTimeZone('Europe/Paris'));
        $now = new \DateTimeImmutable('2026-07-03T10:00:00+02:00');

        $repository->add(FridayEdition::open(str_pad('EDA', 26, '0'), $date, 'Europe/Paris', 100, $now));

        try {
            $repository->add(FridayEdition::open(str_pad('EDB', 26, '0'), $date, 'Europe/Paris', 100, $now));
            self::fail('Expected a ConcurrentCreationException on the duplicate edition.');
        } catch (ConcurrentCreationException) {
            self::assertSame(1, $this->countRows('friday_edition'));
        }
    }

    private function resolver(): ResolveCurrentFridayEdition
    {
        return new ResolveCurrentFridayEdition(
            new DoctrineFridayEditionRepository($this->registry),
            new UlidIdentifierGenerator(),
            new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00')),
            100,
        );
    }
}
