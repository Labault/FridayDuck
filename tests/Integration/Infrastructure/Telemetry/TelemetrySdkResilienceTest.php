<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Telemetry;

use App\Infrastructure\Telemetry\OtelTracer;
use App\Infrastructure\Telemetry\TelemetrySdk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Risques structurants A (non bloquant) et B (pas d'accumulation worker).
 *
 * Avec un Collector INJOIGNABLE : tracer + flusher ne lèvent jamais et restent
 * bornés (dégradation silencieuse). Sur de nombreuses requêtes successives (même
 * "worker"), la mémoire ne croît pas de façon monotone du fait des spans (file
 * bornée + forceFlush effectif).
 */
#[CoversClass(TelemetrySdk::class)]
#[CoversClass(OtelTracer::class)]
final class TelemetrySdkResilienceTest extends TestCase
{
    // Port volontairement fermé : connexion refusée immédiate (pas d'attente réseau).
    private const string DEAD_ENDPOINT = 'http://127.0.0.1:1';

    public function testUnreachableCollectorNeverThrowsAndIsBounded(): void
    {
        $sdk = $this->sdk(self::DEAD_ENDPOINT);
        $tracer = new OtelTracer($sdk);

        $startedAt = hrtime(true);
        $result = $tracer->trace('test.span', ['friday.date' => '2026-07-03'], static fn (): string => 'ok');
        $sdk->forceFlush(); // export vers le hub mort → avalé, jamais propagé
        $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

        self::assertSame('ok', $result); // le travail s'exécute normalement
        self::assertLessThan(5.0, $elapsedSeconds); // borné, pas de blocage
    }

    public function testDisabledSdkIsNoopAndSafe(): void
    {
        $sdk = $this->sdk('');
        $tracer = new OtelTracer($sdk);

        self::assertFalse($sdk->enabled());
        self::assertSame(42, $tracer->trace('test.span', [], static fn (): int => 42));
        self::assertNull($tracer->currentTraceparent());
        $sdk->forceFlush(); // no-op
    }

    public function testSpansDoNotAccumulateAcrossManyRequests(): void
    {
        $sdk = $this->sdk(self::DEAD_ENDPOINT);
        $tracer = new OtelTracer($sdk);

        // Échauffement (stabilise les allocations ponctuelles) puis mesure.
        $this->simulateRequests($tracer, $sdk, 200);
        gc_collect_cycles();
        $baseline = memory_get_usage();

        $this->simulateRequests($tracer, $sdk, 2000);
        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;

        // 2000 spans de plus après échauffement : croissance bornée (file purgée).
        self::assertLessThan(3_000_000, $growth, \sprintf('Croissance mémoire suspecte : %d octets.', $growth));
    }

    private function simulateRequests(OtelTracer $tracer, TelemetrySdk $sdk, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $tracer->trace('coffee.contribution.persist', ['friday.date' => '2026-07-03'], static fn (): bool => true);
            if (0 === $i % 100) {
                $sdk->forceFlush(); // flush « fin de requête » périodique
            }
        }
    }

    private function sdk(string $endpoint): TelemetrySdk
    {
        return new TelemetrySdk($endpoint, 'friday-duck-test', 'test', 'test', new NullLogger(), 1.0);
    }
}
