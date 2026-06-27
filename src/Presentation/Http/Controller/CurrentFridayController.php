<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Friday\GetCurrentFridayHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/friday/current — état temporel courant (§24.1, version Phase 1).
 *
 * Point d'entrée fin : délègue à l'Application, sérialise le modèle de lecture.
 * Réponse réduite { active, date, timezone, status } — calculée, jamais persistée.
 */
final readonly class CurrentFridayController
{
    public function __construct(private GetCurrentFridayHandler $getCurrentFridayHandler)
    {
    }

    #[Route('/api/friday/current', name: 'api_friday_current', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $currentFridayView = ($this->getCurrentFridayHandler)();

        return new JsonResponse([
            'active' => $currentFridayView->active,
            'date' => $currentFridayView->date,
            'timezone' => $currentFridayView->timezone,
            'status' => $currentFridayView->status,
        ]);
    }
}
