<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Health\DatabaseHealthInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sondes de santé d'INFRASTRUCTURE (§31.4). Aucune notion métier (énergie,
 * café, vendredi…) : de la pure plomberie. Deux sondes, deux rôles distincts.
 *
 *  - LIVENESS (`/health`) : « le process répond-il ? ». AUCUNE dépendance testée.
 *    Destinée au HEALTHCHECK Docker récurrent : une sonde qui taperait la base en
 *    boucle provoquerait des restart-loops quand c'est la base — pas l'app — qui
 *    flanche. L'app reste debout et dégrade ; on ne la redémarre pas pour rien.
 *
 *  - READINESS (`/health/ready`) : « peut-on servir le trafic ? ». Ping `SELECT 1`
 *    sur PostgreSQL. C'est le GATE de déploiement (cf. deploy.sh) : 503 si la base
 *    est injoignable → le déploiement échoue. Mercure est VOLONTAIREMENT exclu :
 *    s'il tombe, la page charge quand même (le temps réel dégrade), pas de quoi
 *    bloquer une mise en ligne.
 */
final readonly class HealthController
{
    public function __construct(
        private DatabaseHealthInterface $databaseHealth,
        #[Autowire('%env(string:default::APP_VERSION)%')]
        private string $appVersion,
    ) {
    }

    /** Liveness : ultra-léger, sans dépendance. 200 « OK » tant que le process répond. */
    #[Route('/health', name: 'health_live', methods: ['GET'])]
    public function live(): Response
    {
        return new Response('OK', Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Readiness : 200 si la base répond, 503 sinon. */
    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $databaseUp = $this->databaseHealth->isAvailable();

        return new JsonResponse(
            [
                'status' => $databaseUp ? 'ok' : 'error',
                'db' => $databaseUp ? 'up' : 'down',
                'version' => '' !== $this->appVersion ? $this->appVersion : 'dev',
            ],
            $databaseUp ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
