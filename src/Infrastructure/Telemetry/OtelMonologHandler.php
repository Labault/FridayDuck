<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use Monolog\Level;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;

/**
 * Pont Monolog → OTLP (§26.1) : exporte les logs applicatifs via le LoggerProvider
 * OTel. Émis DANS un span actif, ils portent automatiquement trace_id/span_id —
 * la corrélation logs ↔ traces. SDK désactivé → provider no-op → handler inerte.
 */
final class OtelMonologHandler extends Handler
{
    public function __construct(TelemetrySdk $telemetrySdk, Level $level = Level::Info)
    {
        parent::__construct($telemetrySdk->loggerProvider(), $level);
    }
}
