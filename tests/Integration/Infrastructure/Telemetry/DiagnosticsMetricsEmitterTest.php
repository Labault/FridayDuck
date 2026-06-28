<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Telemetry;

use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Infrastructure\Clock\FrozenClock;
use App\Infrastructure\Identity\UlidIdentifierGenerator;
use App\Infrastructure\Persistence\DoctrineFridayEditionRepository;
use App\Infrastructure\Persistence\DoctrineOutboxEntryRepository;
use App\Infrastructure\Telemetry\DiagnosticsMetricsEmitter;
use App\Tests\Double\RecordingMetrics;
use App\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Les jauges de diagnostic (§26.7) sont émises avec des noms qui correspondent aux
 * expressions d'alerte. Sur un vendredi avec une édition ouverte : pas de divergence.
 */
#[CoversClass(DiagnosticsMetricsEmitter::class)]
final class DiagnosticsMetricsEmitterTest extends DatabaseTestCase
{
    private const string TZ = 'Europe/Paris';

    public function testEmitsDiagnosticGaugesOnAnOpenFriday(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-07-03T10:00:00+02:00')); // vendredi, avant 14:00
        (new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $clock, 100))
            ->resolve(new \DateTimeImmutable('2026-07-03'), self::TZ);

        $metrics = new RecordingMetrics();
        $this->emitter($metrics, $clock, appFakeNow: '')->emit();

        self::assertSame(1, $metrics->lastGauge('duck.clock.friday_active'));
        self::assertSame(1, $metrics->lastGauge('duck.edition.open'));
        self::assertSame(0, $metrics->lastGauge('duck.clock.status_divergence'));
        self::assertSame(0, $metrics->lastGauge('duck.vote.unresolved_after_close'));
        self::assertSame(0, $metrics->lastGauge('mercure.outbox.backlog'));
        self::assertSame(0, $metrics->lastGauge('duck.app_fake_now_active'));
    }

    public function testAppFakeNowGaugeReflectsTheEnvAndDivergenceOnDormantDay(): void
    {
        // Mardi (dormant) avec une édition de vendredi restée OUVERTE → divergence.
        $tuesday = new FrozenClock(new \DateTimeImmutable('2026-06-30T10:00:00+02:00'));
        (new ResolveCurrentFridayEdition(new DoctrineFridayEditionRepository($this->registry), new UlidIdentifierGenerator(), $tuesday, 100))
            ->resolve(new \DateTimeImmutable('2026-06-26'), self::TZ); // vendredi écoulé, édition OPEN

        $metrics = new RecordingMetrics();
        $this->emitter($metrics, $tuesday, appFakeNow: '2026-07-03T10:00:00+02:00')->emit();

        self::assertSame(0, $metrics->lastGauge('duck.clock.friday_active'));
        self::assertSame(1, $metrics->lastGauge('duck.clock.status_divergence')); // dormant mais OPEN
        self::assertSame(1, $metrics->lastGauge('duck.app_fake_now_active'));
    }

    private function emitter(RecordingMetrics $metrics, FrozenClock $clock, string $appFakeNow): DiagnosticsMetricsEmitter
    {
        return new DiagnosticsMetricsEmitter(
            new \App\Domain\Friday\FridayCalendar($clock, self::TZ),
            new DoctrineFridayEditionRepository($this->registry),
            new DoctrineOutboxEntryRepository($this->registry),
            $this->registry,
            $metrics,
            $clock,
            self::TZ,
            $appFakeNow,
            'test',
        );
    }
}
