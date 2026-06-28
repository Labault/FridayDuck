<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\RealTime\OutboxRelay;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler du relais (§20.6, invariant E). Mince : délègue à {@see OutboxRelay}.
 * Une publication en échec lève {@see \App\Application\RealTime\OutboxRelayFailed}
 * → retries Messenger → file d'échec après seuil (§25.4).
 */
#[AsMessageHandler]
final readonly class RelayOutboxHandler
{
    public function __construct(private OutboxRelay $outboxRelay)
    {
    }

    public function __invoke(RelayOutbox $relayOutbox): void
    {
        $this->outboxRelay->relayPending();
    }
}
