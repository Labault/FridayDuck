<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * Points d'instrumentation du relais (§26.4) — POSÉS ici, EXPORTÉS en Phase 7.
 *
 * Mappent vers `mercure.publish.count` (succès), `mercure.publish.failure` (échec)
 * et la profondeur du backlog outbox. L'implémentation par défaut est un no-op
 * ({@see \App\Infrastructure\RealTime\NullRelayMetrics}) : aucune dépendance OTel
 * n'entre tant que la Phase 7 n'a pas branché l'export.
 */
interface RelayMetrics
{
    public function publishSucceeded(): void;

    public function publishFailed(): void;

    public function backlogDepth(int $depth): void;
}
