<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Friday\GetCurrentFridayHandler;
use App\Application\RealTime\FridayTopic;
use App\Presentation\Http\Visitor\VisitorCookieResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * GET / — page du canard (Phase 2b-ii).
 *
 * État initial par VALEURS TWIG (§19.3) : on résout l'identité (cookie) et on
 * RÉUTILISE le service applicatif de GET /api/friday/current (même handler — il
 * résout-ou-crée l'édition et enregistre la visite, comme en 2a-i), puis on
 * rend energy/energyVersion/remainingCoffees/active/status dans
 * `stimulus_controller('duck', …)`. Le contrôleur lit ces valeurs au connect()
 * et amorce la barrière de version. AUCUN fetch client au chargement.
 */
final readonly class DuckController
{
    public function __construct(
        private Environment $twigEnvironment,
        private GetCurrentFridayHandler $getCurrentFridayHandler,
        private VisitorCookieResolver $visitorCookieResolver,
    ) {
    }

    #[Route('/', name: 'duck_demo', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $resolvedVisitorCookie = $this->visitorCookieResolver->readOrIssue($request);
        $currentFridayView = ($this->getCurrentFridayHandler)($resolvedVisitorCookie->hash);

        $response = new Response($this->twigEnvironment->render('duck/demo.html.twig', [
            'friday' => $currentFridayView,
            // Topic Mercure de l'édition (§20.2) — Twig en dérive l'URL d'abonnement.
            'mercureTopic' => FridayTopic::forDateString($currentFridayView->date),
        ]));

        if ($resolvedVisitorCookie->issued instanceof Cookie) {
            $response->headers->setCookie($resolvedVisitorCookie->issued);
        }

        return $response;
    }
}
