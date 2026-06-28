<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Telemetry;

use App\Infrastructure\Telemetry\OtelRelayMetrics;
use App\Tests\Double\RecordingMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * OtelRelayMetrics (item 5) émet les métriques de relais Mercure (§26.3) et la
 * profondeur du backlog (§26.4) via le port Metrics — remplace NullRelayMetrics.
 */
#[CoversClass(OtelRelayMetrics::class)]
final class OtelRelayMetricsTest extends TestCase
{
    public function testEmitsPublishCountFailureAndBacklog(): void
    {
        $metrics = new RecordingMetrics();
        $relayMetrics = new OtelRelayMetrics($metrics);

        $relayMetrics->publishSucceeded();
        $relayMetrics->publishSucceeded();
        $relayMetrics->publishFailed();
        $relayMetrics->backlogDepth(7);

        self::assertSame(2, $metrics->counterTotal('mercure.publish.count'));
        self::assertSame(1, $metrics->counterTotal('mercure.publish.failure'));
        self::assertSame(7, $metrics->lastGauge('mercure.outbox.backlog'));
    }
}
