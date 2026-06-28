<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Telemetry\Metrics;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/telemetry — ingestion de la télémétrie FRONT de SANTÉ (§26.5).
 *
 * Volontairement MINIMALE et défensive : seules des métriques d'une LISTE BLANCHE
 * sont acceptées (santé du rendu / connexion Mercure), avec une valeur numérique
 * et un unique label `state` borné (faible cardinalité). AUCUNE donnée perso ni
 * comportement (risque C) : pas d'identité, pas d'attribut libre. Répond 204 sans
 * jamais bloquer le rendu — l'enregistrement passe par le port non bloquant.
 */
final readonly class FrontTelemetryController
{
    /**
     * Métriques front autorisées → type d'instrument.
     *
     * @var array<non-empty-string, 'counter'|'gauge'|'histogram'>
     */
    private const array ALLOWED = [
        'duck.animation.init.duration' => 'histogram',
        'duck.animation.init.failure' => 'counter',
        'duck.animation.sequence.failure' => 'counter',
        'duck.mercure.connection.state' => 'gauge',
        'duck.mercure.reconnect.count' => 'counter',
        'duck.svg.missing_target' => 'counter',
    ];

    public function __construct(private Metrics $metrics)
    {
    }

    #[Route('/api/telemetry', name: 'api_front_telemetry', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $entries = \is_array($payload) && isset($payload['metrics']) && \is_array($payload['metrics']) ? $payload['metrics'] : [];

        foreach ($entries as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $this->record($entry);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $entry
     */
    private function record(array $entry): void
    {
        $name = $entry['name'] ?? null;
        if (!\is_string($name) || !\array_key_exists($name, self::ALLOWED)) {
            return; // hors liste blanche → ignoré
        }

        $value = $entry['value'] ?? 1;
        if (!\is_int($value) && !\is_float($value)) {
            return;
        }

        $attributes = $this->safeState($entry['state'] ?? null);

        match (self::ALLOWED[$name]) {
            'counter' => $this->metrics->counter($name, max(0, (int) $value), $attributes),
            'gauge' => $this->metrics->gauge($name, $value, $attributes),
            'histogram' => $this->metrics->histogram($name, $value, $attributes),
        };
    }

    /**
     * @return array<non-empty-string, string>
     */
    private function safeState(mixed $state): array
    {
        // Unique label borné (santé), alphanumérique court → pas de PII, cardinalité maîtrisée.
        if (\is_string($state) && 1 === preg_match('/^[a-z0-9_-]{1,32}$/i', $state)) {
            return ['state' => $state];
        }

        return [];
    }
}
