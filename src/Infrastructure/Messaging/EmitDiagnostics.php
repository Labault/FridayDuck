<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

/**
 * Message périodique (§26.7) déclenchant l'émission des jauges de diagnostic
 * (horloge/statut, backlog, file d'échec, APP_FAKE_NOW). Sans charge.
 */
final readonly class EmitDiagnostics
{
}
