<?php

declare(strict_types=1);

namespace App\Infrastructure\RealTime;

use App\Application\RealTime\RealTimeTransport;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Implémentation Mercure du transport temps réel (§20). Publie un Update PUBLIC
 * sur le topic de l'édition (abonnement anonyme, état global, invariant B).
 *
 * Laisse remonter toute défaillance du hub : le relais la gère (retry → file
 * d'échec, §25.4). C'est l'inverse de l'ancien best-effort « loggé et avalé ».
 */
final readonly class MercureRealTimeTransport implements RealTimeTransport
{
    public function __construct(private HubInterface $hub)
    {
    }

    public function publish(string $topic, string $payload): void
    {
        $this->hub->publish(new Update($topic, $payload));
    }
}
