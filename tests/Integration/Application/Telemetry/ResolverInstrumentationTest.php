<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Telemetry;

use App\Application\Accessory\AccessoryOptionsReader;
use App\Application\Accessory\ResolveAccessoryOptions;
use App\Application\Accessory\ResolveAccessoryWinner;
use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\Visitor\RecordFridayVisit;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineAccessoryRepository;
use App\Infrastructure\Persistence\DoctrineAnonymousVisitorRepository;
use App\Infrastructure\Persistence\DoctrineFridayAccessoryOptionRepository;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineFridayVisitRepository;
use App\Infrastructure\Persistence\DoctrineTransactional;
use App\Tests\Double\RecordingMetrics;
use App\Tests\Double\RecordingTracer;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Spans + métriques des resolveurs (7a différés, finis en 7b). Attributs = contexte
 * (date), JAMAIS d'identité (risque C). Émis par le Tracer/Metrics OPTIONNELS
 * (autowirés en prod) ; ici injectés via doubles enregistreurs.
 */
#[CoversClass(ResolveCurrentFridayEdition::class)]
#[CoversClass(ResolveAnonymousVisitor::class)]
#[CoversClass(ResolveAccessoryWinner::class)]
#[CoversClass(RecordFridayVisit::class)]
final class ResolverInstrumentationTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';

    public function testFridayCurrentResolveEmitsSpanWithDateContext(): void
    {
        $tracer = new RecordingTracer();
        $resolver = new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $this->clock(), 100, $tracer);

        $resolver->resolve($this->friday(), self::TZ);

        $span = $tracer->span('friday.current.resolve');
        self::assertNotNull($span);
        self::assertSame('2026-07-03', $span['attributes']['friday.date']);
    }

    public function testVisitorResolveSpanCarriesNoIdentity(): void
    {
        $hash = 'secret-visitor-hash';
        $tracer = new RecordingTracer();
        $resolver = new ResolveAnonymousVisitor(new DoctrineAnonymousVisitorRepository($this->registry), new UlidIdentifierGenerator(), $this->clock(), $tracer);

        $resolver->resolve($hash);

        $span = $tracer->span('visitor.resolve');
        self::assertNotNull($span);
        self::assertSame([], $span['attributes']); // aucune donnée perso
        foreach ($span['attributes'] as $value) {
            self::assertStringNotContainsString($hash, (string) $value);
        }
    }

    public function testUniqueVisitorsMetricCountsFirstVisitOnly(): void
    {
        $editionId = (new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $this->clock(), 100))
            ->resolve($this->friday(), self::TZ)->id();

        $metrics = new RecordingMetrics();
        $recorder = new RecordFridayVisit(new DoctrineFridayVisitRepository($this->registry), new UlidIdentifierGenerator(), $this->clock(), $metrics);

        $recorder->record($editionId, 'visitor-1');
        $recorder->record($editionId, 'visitor-1'); // re-visite → touch, pas un nouveau

        self::assertSame(1, $metrics->counterTotal('duck.friday.unique_visitors'));
    }

    public function testWinnerResolveEmitsSpanAndMetricOnFirstResolutionOnly(): void
    {
        $edition = (new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $this->clock(), 100))
            ->resolve($this->friday(), self::TZ);
        (new ResolveAccessoryOptions(new DoctrineAccessoryRepository($this->registry), new DoctrineFridayAccessoryOptionRepository($this->registry), new UlidIdentifierGenerator()))
            ->resolve($edition->id(), $this->friday());

        $tracer = new RecordingTracer();
        $metrics = new RecordingMetrics();
        $resolver = new ResolveAccessoryWinner(
            new DoctrineTransactional($this->registry),
            new DoctrineFridayEditionRepository($this->registry),
            new AccessoryOptionsReader(new DoctrineAccessoryRepository($this->registry), new DoctrineFridayAccessoryOptionRepository($this->registry)),
            $tracer,
            $metrics,
        );

        $resolver->resolve($this->friday(), self::TZ); // 1er calcul → fige + métrique
        $resolver->resolve($this->friday(), self::TZ); // relecture immuable → pas de métrique

        self::assertNotNull($tracer->span('accessory.winner.resolve'));
        self::assertSame(1, $metrics->counterTotal('duck.accessory.winner'));
    }

    private function friday(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-03');
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable('2026-07-03T15:00:00+02:00'));
    }
}
