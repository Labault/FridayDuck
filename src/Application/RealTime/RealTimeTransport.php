<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * Port de PUBLICATION effective sur le canal temps réel (§20.2) — utilisé par le
 * RELAIS de l'outbox, pas par le chemin requête.
 *
 * Distinct de {@see DomainEventPublisher} (qui écrit l'outbox) : ici on pousse une
 * charge déjà sérialisée sur un topic. Implémenté par Mercure en production.
 *
 * Contrairement au publisher, ce port PEUT lever (hub injoignable) : le relais
 * intercepte, incrémente la tentative, laisse la ligne non publiée et rejoue
 * (at-least-once, §25.4).
 */
interface RealTimeTransport
{
    public function publish(string $topic, string $payload): void;
}
