<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Coffee;

use App\Application\Coffee\ServeCoffee;
use App\Application\Coffee\ServeCoffeeHandler;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Friday\FridayCalendar;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Infrastructure\Persistence\DoctrineCoffeeContributionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Tests\Double\NullDomainEventPublisher;
use App\Tests\Double\RecordingMetrics;
use App\Tests\Double\RecordingTracer;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Instrumentation du café (§26.2/26.4) : la chaîne de spans attendue, les métriques
 * métier à la mutation, et ZÉRO donnée perso (risque C).
 */
#[CoversClass(ServeCoffee::class)]
final class CoffeeInstrumentationTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';
    private const string HASH = 'a-visitor-hash-that-must-never-leak';

    private RecordingTracer $tracer;

    private RecordingMetrics $metrics;

    public function testAcceptedCoffeeProducesTheExpectedSpanChain(): void
    {
        $this->handler()->handle(self::HASH, 'action-1');

        self::assertSame(
            ['coffee.contribution.validate', 'energy.recalculate', 'coffee.contribution.persist'],
            $this->tracer->names(),
        );

        $validate = $this->tracer->span('coffee.contribution.validate');
        self::assertNotNull($validate);
        self::assertSame('2026-07-03', $validate['attributes']['friday.date']);
        self::assertSame(0, $validate['attributes']['coffee.contribution.count']);

        $recalculate = $this->tracer->span('energy.recalculate');
        self::assertNotNull($recalculate);
        self::assertSame(1, $recalculate['attributes']['duck.energy']);
    }

    public function testBusinessMetricsReflectTheMutation(): void
    {
        $handler = $this->handler();
        $handler->handle(self::HASH, 'a1');
        $handler->handle(self::HASH, 'a2');

        self::assertSame(2, $this->metrics->counterTotal('duck.coffee.total'));
        self::assertSame(2, $this->metrics->lastGauge('duck.energy')); // énergie courante
        self::assertNotSame([], $this->metrics->named('duck.energy.state'));
    }

    public function testQuotaRejectionIsCounted(): void
    {
        $handler = $this->handler();
        $handler->handle(self::HASH, 'a1');
        $handler->handle(self::HASH, 'a2');
        $handler->handle(self::HASH, 'a3');
        $handler->handle(self::HASH, 'a4'); // quota

        self::assertSame(1, $this->metrics->counterTotal('duck.coffee.rejected'));
        self::assertSame(3, $this->metrics->counterTotal('duck.coffee.total'));
    }

    public function testNoPersonalDataInSpansOrMetrics(): void
    {
        $this->handler()->handle(self::HASH, 'action-1');

        foreach ($this->tracer->spans as $span) {
            foreach ($span['attributes'] as $key => $value) {
                self::assertStringNotContainsStringIgnoringCase('cookie', (string) $key);
                self::assertStringNotContainsStringIgnoringCase('visitor', (string) $key);
                self::assertStringNotContainsString(self::HASH, (string) $value);
            }
        }

        foreach ($this->metrics->records as $record) {
            foreach ($record['attributes'] as $key => $value) {
                self::assertStringNotContainsStringIgnoringCase('cookie', (string) $key);
                self::assertStringNotContainsString(self::HASH, (string) $value);
            }
        }
    }

    private function handler(): ServeCoffeeHandler
    {
        $friday = new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00'));
        $this->tracer = new RecordingTracer();
        $this->metrics = new RecordingMetrics();

        return new ServeCoffeeHandler(
            new FridayCalendar($friday, self::TZ),
            new ResolveAnonymousVisitor(new DoctrineAnonymousVisitorRepository($this->registry), new UlidIdentifierGenerator(), $friday),
            new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $friday, 100),
            new ServeCoffee(
                new DoctrineTransactional($this->registry),
                new DoctrineFridayEditionRepository($this->registry),
                new DoctrineCoffeeContributionRepository($this->registry),
                new UlidIdentifierGenerator(),
                $friday,
                new NullDomainEventPublisher(),
                $this->tracer,
                $this->metrics,
            ),
        );
    }
}
