<?php

declare(strict_types=1);

namespace App\Application\Telemetry;

/**
 * Port de traçage métier (§26.2). PHP pur — l'implémentation OTLP vit dans
 * l'Infrastructure ; les services métier ne dépendent que de cette abstraction.
 *
 * RISQUE A (non bloquant) & C (zéro donnée perso) sont des contrats de
 * l'implémentation : un export lent/injoignable ne bloque jamais ; les attributs
 * passés ici décrivent le CONTEXTE métier (édition, date, type), jamais l'identité
 * brute du visiteur.
 */
interface Tracer
{
    /**
     * Exécute $work dans un span nommé. Le span devient enfant du span actif
     * (p. ex. le span de requête HTTP). Auto-clôture + enregistrement d'exception.
     *
     * @template T
     *
     * @param non-empty-string                               $name
     * @param array<non-empty-string, bool|int|float|string> $attributes contexte métier (jamais d'identité brute)
     * @param callable(SpanScope): T                         $work
     *
     * @return T
     */
    public function trace(string $name, array $attributes, callable $work): mixed;

    /**
     * Traceparent W3C du span actif (pour recoudre une frontière async), ou null.
     */
    public function currentTraceparent(): ?string;

    /**
     * Exécute $work dans un span ENFANT du contexte distant décrit par $traceparent
     * (W3C) — recoud la frontière async outbox → relais (§26.2, propagation).
     *
     * @template T
     *
     * @param non-empty-string                               $name
     * @param array<non-empty-string, bool|int|float|string> $attributes
     * @param callable(SpanScope): T                         $work
     *
     * @return T
     */
    public function traceLinkedTo(string $name, ?string $traceparent, array $attributes, callable $work): mixed;
}
