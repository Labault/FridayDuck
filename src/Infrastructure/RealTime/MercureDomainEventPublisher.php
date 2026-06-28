<?php

declare(strict_types=1);

namespace App\Infrastructure\RealTime;

use App\Application\RealTime\DomainEvent;
use App\Application\RealTime\DomainEventPublisher;
use App\Application\RealTime\FridayTopic;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Implémentation Mercure du port (§20). Publie un Update PUBLIC sur le topic de
 * l'édition : `{ type, …payload }` (§24.5). Abonnement anonyme, état global
 * uniquement (invariant B).
 *
 * Best-effort : toute défaillance du hub est journalisée et AVALÉE — jamais
 * propagée (l'état est déjà committé ; le temps réel est transient, §20.6).
 */
final readonly class MercureDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(\DateTimeImmutable $fridayDate, DomainEvent $domainEvent): void
    {
        try {
            $payload = json_encode(['type' => $domainEvent->type()] + $domainEvent->payload(), \JSON_THROW_ON_ERROR);

            // Update PUBLIC (3e argument `private` à false par défaut).
            $this->hub->publish(new Update(FridayTopic::forDate($fridayDate), $payload));
        } catch (\Throwable $exception) {
            $this->logger->error('Échec de publication Mercure d’un événement de domaine.', [
                'exception' => $exception,
                'eventType' => $domainEvent->type(),
                'friday' => $fridayDate->format('Y-m-d'),
            ]);
        }
    }
}
