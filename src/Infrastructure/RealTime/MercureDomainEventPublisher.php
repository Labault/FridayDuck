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
 * SUPERSÉDÉ en Phase 6b : le port pointe désormais sur
 * {@see OutboxDomainEventPublisher} (écriture transactionnelle) ; la publication
 * Mercure passe par {@see MercureRealTimeTransport} (côté relais). Conservé NON
 * CÂBLÉ le temps de la bascule (§20.6) — retirer une fois la transition stabilisée.
 *
 * Implémentation Mercure best-effort historique : publie un Update PUBLIC sur le
 * topic de l'édition (`{ type, …payload }`, §24.5), défaillance du hub journalisée
 * et avalée.
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
