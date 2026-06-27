<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Health\DatabaseHealthInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health check d'INFRASTRUCTURE (§31.4).
 *
 * 200 si l'application a amorcé ET que la base répond, 503 sinon. Aucune notion
 * métier (énergie, café, vendredi…) : ce n'est qu'une sonde de plomberie.
 */
final readonly class HealthController
{
    public function __construct(
        private DatabaseHealthInterface $databaseHealth,
        #[Autowire('%env(string:default::APP_VERSION)%')]
        private string $appVersion,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $databaseUp = $this->databaseHealth->isAvailable();

        return new JsonResponse(
            [
                'status' => $databaseUp ? 'ok' : 'degraded',
                'version' => '' !== $this->appVersion ? $this->appVersion : 'dev',
                'checks' => [
                    'database' => $databaseUp ? 'up' : 'down',
                ],
            ],
            $databaseUp ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
