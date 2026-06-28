<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

/**
 * Message déclenchant un cycle de relais de l'outbox (§20.6). Sans charge : le
 * handler lit lui-même les lignes non publiées. Dispatché périodiquement par le
 * Scheduler (filet de rattrapage) ; le relais est le MÊME que la commande manuelle.
 */
final readonly class RelayOutbox
{
}
