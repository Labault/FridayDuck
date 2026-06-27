<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Visitor;

use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Tests\Integration\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ResolveAnonymousVisitor::class)]
#[CoversClass(DoctrineAnonymousVisitorRepository::class)]
final class AnonymousVisitorIdentityTest extends DatabaseTestCase
{
    public function testSameHashYieldsStableIdentityAndCountsVisits(): void
    {
        $resolve = $this->resolver();
        $hash = hash('sha256', 'cookie-token-A');

        $first = $resolve->resolve($hash);
        $second = $resolve->resolve($hash);

        self::assertTrue($first->isNew);
        self::assertFalse($second->isNew);
        self::assertSame($first->visitor->id(), $second->visitor->id());
        self::assertSame(1, $this->countRows('anonymous_visitor'));

        $connection = $this->registry->getConnection();
        \assert($connection instanceof Connection);
        self::assertSame(2, (int) $connection->fetchOne('SELECT total_visits FROM anonymous_visitor'));
    }

    public function testDistinctHashYieldsDistinctVisitor(): void
    {
        $resolve = $this->resolver();

        $a = $resolve->resolve(hash('sha256', 'cookie-token-A'));
        $b = $resolve->resolve(hash('sha256', 'cookie-token-B'));

        self::assertNotSame($a->visitor->id(), $b->visitor->id());
        self::assertSame(2, $this->countRows('anonymous_visitor'));
    }

    private function resolver(): ResolveAnonymousVisitor
    {
        return new ResolveAnonymousVisitor(
            new DoctrineAnonymousVisitorRepository($this->registry),
            new UlidIdentifierGenerator(),
            new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00')),
        );
    }
}
