<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Coffee\CoffeeOutcomeStatus;
use App\Application\Coffee\CoffeeResult;
use App\Application\Coffee\ServeCoffeeHandler;
use App\Domain\Shared\Clock\ClockInterface;
use App\Presentation\Http\Visitor\VisitorCookieResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/friday/current/coffees — offrir un café (§24.2).
 *
 * EXIGE l'en-tête `Idempotency-Key` (le client-action-id) : c'est lui qui rend
 * le retry sûr ; absent → erreur de validation (jamais de génération serveur,
 * qui tuerait l'idempotence). La garde temporelle (NOT_FRIDAY) et la transaction
 * vivent dans l'Application ; ici on ne fait que traduire en HTTP. Un rejeu
 * idempotent est un succès 200, pas une erreur.
 */
final readonly class ServeCoffeeController
{
    public function __construct(
        private ServeCoffeeHandler $serveCoffeeHandler,
        private VisitorCookieResolver $visitorCookieResolver,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/api/friday/current/coffees', name: 'api_friday_coffees', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $resolvedVisitorCookie = $this->visitorCookieResolver->readOrIssue($request);
        $clientActionId = $request->headers->get('Idempotency-Key');

        if (!\is_string($clientActionId) || '' === trim($clientActionId)) {
            $response = $this->error(
                'INVALID_IDEMPOTENCY_KEY',
                "L'en-tête Idempotency-Key est requis.",
                Response::HTTP_BAD_REQUEST,
            );
        } else {
            $outcome = $this->serveCoffeeHandler->handle($resolvedVisitorCookie->hash, trim($clientActionId));
            $response = match ($outcome->status) {
                CoffeeOutcomeStatus::NotFriday => $this->error(
                    'NOT_FRIDAY',
                    'Le canard ne sert le café que le vendredi.',
                    Response::HTTP_CONFLICT,
                ),
                CoffeeOutcomeStatus::LimitReached => $this->error(
                    'COFFEE_LIMIT_REACHED',
                    'Quota de trois cafés atteint pour ce vendredi.',
                    Response::HTTP_TOO_MANY_REQUESTS,
                ),
                CoffeeOutcomeStatus::Served => $this->served(
                    $outcome->result ?? throw new \LogicException('Issue « servie » sans résultat.'),
                ),
            };
        }

        if ($resolvedVisitorCookie->issued instanceof Cookie) {
            $response->headers->setCookie($resolvedVisitorCookie->issued);
        }

        return $response;
    }

    private function served(CoffeeResult $coffeeResult): JsonResponse
    {
        return new JsonResponse([
            'accepted' => true,
            // Distingue acceptation réelle d'un rejeu idempotent (les deux en 200,
            // §8.5) : le front n'anime coffee_receive que sur une vraie acceptation.
            'replayed' => $coffeeResult->replayed,
            'coffeeContributionId' => $coffeeResult->contributionId,
            'previousEnergy' => $coffeeResult->previousEnergy,
            'currentEnergy' => $coffeeResult->currentEnergy,
            'energyVersion' => $coffeeResult->energyVersion,
            'coffeeCount' => $coffeeResult->coffeeCount,
            'overcaffeinationCount' => $coffeeResult->overcaffeinationCount,
            'remainingCoffeesForVisitor' => $coffeeResult->remainingCoffees,
            'reachedThreshold' => $coffeeResult->reachedThreshold,
            'serverTime' => $this->clock->now()->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
