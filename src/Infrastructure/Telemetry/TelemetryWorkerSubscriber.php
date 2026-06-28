<?php

declare(strict_types=1);

namespace App\Infrastructure\Telemetry;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Discipline télémétrie hors HTTP (§22.2, risque B) : CLI et workers Messenger.
 *
 * - À l'entrée d'une commande : enregistre les providers GLOBAUX, pour que
 *   l'auto-instrumentation (Doctrine/Messenger) exporte par les bons pipelines.
 * - Après CHAQUE message traité/échoué d'un worker : force le flush → les spans ne
 *   s'accumulent pas dans un `messenger:consume` longue durée (même garantie que le
 *   flush par requête HTTP).
 * - À la FIN de toute commande (TERMINATE) : flush final. Indispensable aux
 *   commandes COURTES hors worker — notamment `app:outbox:relay` (relais en boucle,
 *   §20.6) : sans lui, le span `mercure.update.publish` (enfant de la trace requête
 *   via le traceparent de l'outbox) et la métrique de publication seraient créés
 *   puis PERDUS à la sortie du process, le batch processor n'ayant jamais vidé.
 *
 * Court-circuité quand la télémétrie est désactivée (endpoint vide).
 */
final readonly class TelemetryWorkerSubscriber implements EventSubscriberInterface
{
    public function __construct(private TelemetrySdk $telemetrySdk)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onCommand',
            ConsoleEvents::TERMINATE => 'onCommandTerminate',
            WorkerMessageHandledEvent::class => 'onMessageProcessed',
            WorkerMessageFailedEvent::class => 'onMessageProcessed',
        ];
    }

    public function onCommand(): void
    {
        if ($this->telemetrySdk->enabled()) {
            $this->telemetrySdk->boot();
        }
    }

    public function onCommandTerminate(): void
    {
        // Flush final de TOUTE commande (relais outbox, runbook, etc.) : sans lui,
        // les spans/métriques d'une commande courte hors worker ne sont jamais vidés.
        if ($this->telemetrySdk->enabled()) {
            $this->telemetrySdk->forceFlush();
        }
    }

    public function onMessageProcessed(): void
    {
        if ($this->telemetrySdk->enabled()) {
            $this->telemetrySdk->forceFlush();
        }
    }
}
