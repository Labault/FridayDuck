<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Advice\AdviceReactionOutcomeStatus;
use App\Application\Advice\AdviceReactionResult;
use App\Application\Advice\ReactToAdviceHandler;
use App\Presentation\Http\Visitor\VisitorCookieResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PUT /api/friday/current/advice-reaction — réagir au conseil (§24.4).
 *
 * PUT = upsert : poser OU changer sa réaction (mutable). Pas de clé d'idempotence
 * (UNIQUE(edition, visitor) côté serveur). Un re-PUT de la même réaction est un
 * succès no-op. La garde temporelle et la transaction vivent dans l'Application.
 */
final readonly class ReactToAdviceController
{
    public function __construct(
        private ReactToAdviceHandler $reactToAdviceHandler,
        private VisitorCookieResolver $visitorCookieResolver,
    ) {
    }

    #[Route('/api/friday/current/advice-reaction', name: 'api_friday_advice_reaction', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        $resolvedVisitorCookie = $this->visitorCookieResolver->readOrIssue($request);
        $reaction = $this->readReaction($request);

        if (null === $reaction) {
            $response = $this->refused('INVALID_REACTION', Response::HTTP_UNPROCESSABLE_ENTITY);
        } else {
            $outcome = $this->reactToAdviceHandler->handle($resolvedVisitorCookie->hash, $reaction);
            $response = match ($outcome->status) {
                AdviceReactionOutcomeStatus::NotFriday => $this->refused('NOT_FRIDAY', Response::HTTP_CONFLICT),
                AdviceReactionOutcomeStatus::InvalidReaction => $this->refused('INVALID_REACTION', Response::HTTP_UNPROCESSABLE_ENTITY),
                AdviceReactionOutcomeStatus::Recorded => $this->recorded(
                    $outcome->result ?? throw new \LogicException('Issue « enregistrée » sans résultat.'),
                ),
            };
        }

        if ($resolvedVisitorCookie->issued instanceof Cookie) {
            $response->headers->setCookie($resolvedVisitorCookie->issued);
        }

        return $response;
    }

    private function readReaction(Request $request): ?string
    {
        $decoded = json_decode((string) $request->getContent(), true);
        if (!\is_array($decoded)) {
            return null;
        }

        $reaction = $decoded['reaction'] ?? null;

        return \is_string($reaction) && '' !== trim($reaction) ? trim($reaction) : null;
    }

    private function recorded(AdviceReactionResult $adviceReactionResult): JsonResponse
    {
        return new JsonResponse([
            'accepted' => true,
            'changed' => $adviceReactionResult->changed,
            'reaction' => $adviceReactionResult->reaction->value,
            'adviceSequence' => $adviceReactionResult->adviceSequence,
            'reactions' => [
                'CONCERNING' => $adviceReactionResult->concerning,
                'ALREADY_DONE' => $adviceReactionResult->alreadyDone,
                'TAKING_NOTES' => $adviceReactionResult->takingNotes,
            ],
        ]);
    }

    private function refused(string $reason, int $status): JsonResponse
    {
        return new JsonResponse(['accepted' => false, 'reason' => $reason], $status);
    }
}
