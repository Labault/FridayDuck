<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Infrastructure\Telemetry\DiagnosticsMetricsEmitter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler du tick de diagnostic (§26.7). Mince : délègue à l'émetteur de jauges.
 */
#[AsMessageHandler]
final readonly class EmitDiagnosticsHandler
{
    public function __construct(private DiagnosticsMetricsEmitter $diagnosticsMetricsEmitter)
    {
    }

    public function __invoke(EmitDiagnostics $emitDiagnostics): void
    {
        $this->diagnosticsMetricsEmitter->emit();
    }
}
