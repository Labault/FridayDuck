<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Friday\GetCurrentFridayHandler;
use App\Presentation\Http\Visitor\VisitorCookieResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/friday/current — état courant (§24.1, version Phase 2a-ii).
 *
 * Point d'entrée fin : résout l'identité par cookie, délègue à l'Application
 * (qui résout/crée l'édition et trace la visite le vendredi), sérialise. Pose le
 * cookie d'identité s'il était absent. `active`/`status` restent issus de
 * l'horloge ; énergie/cafés/surcaféination de l'édition persistée.
 */
final readonly class CurrentFridayController
{
    public function __construct(
        private GetCurrentFridayHandler $getCurrentFridayHandler,
        private VisitorCookieResolver $visitorCookieResolver,
    ) {
    }

    #[Route('/api/friday/current', name: 'api_friday_current', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $resolvedVisitorCookie = $this->visitorCookieResolver->readOrIssue($request);
        $currentFridayView = ($this->getCurrentFridayHandler)($resolvedVisitorCookie->hash);

        $jsonResponse = new JsonResponse([
            'active' => $currentFridayView->active,
            'date' => $currentFridayView->date,
            'timezone' => $currentFridayView->timezone,
            'status' => $currentFridayView->status,
            'energy' => $currentFridayView->energy,
            'energyVersion' => $currentFridayView->energyVersion,
            'coffeeCount' => $currentFridayView->coffeeCount,
            'overcaffeinationCount' => $currentFridayView->overcaffeinationCount,
            'visitor' => [
                'isNew' => $currentFridayView->visitorIsNew,
                'remainingCoffees' => $currentFridayView->remainingCoffees,
            ],
        ]);

        if ($resolvedVisitorCookie->issued instanceof Cookie) {
            $jsonResponse->headers->setCookie($resolvedVisitorCookie->issued);
        }

        return $jsonResponse;
    }
}
