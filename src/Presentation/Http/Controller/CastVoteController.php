<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Accessory\AcceptedVote;
use App\Application\Accessory\AccessoryWinnerView;
use App\Application\Accessory\CastVoteHandler;
use App\Application\Accessory\VoteOutcomeStatus;
use App\Presentation\Http\Visitor\VisitorCookieResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/friday/current/accessory-votes — voter pour un accessoire (§24.3).
 *
 * Point d'entrée fin : résout l'identité par cookie, lit `{ accessory: <code> }`,
 * délègue (garde temporelle + transaction dans l'Application), projette en HTTP.
 * VOTE_CLOSED renvoie le gagnant (§10.6).
 */
final readonly class CastVoteController
{
    public function __construct(
        private CastVoteHandler $castVoteHandler,
        private VisitorCookieResolver $visitorCookieResolver,
    ) {
    }

    #[Route('/api/friday/current/accessory-votes', name: 'api_friday_accessory_votes', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $resolvedVisitorCookie = $this->visitorCookieResolver->readOrIssue($request);
        $accessoryCode = $this->readAccessoryCode($request);

        if (null === $accessoryCode) {
            $response = $this->refused('INVALID_ACCESSORY', Response::HTTP_UNPROCESSABLE_ENTITY);
        } else {
            $outcome = $this->castVoteHandler->handle($resolvedVisitorCookie->hash, $accessoryCode);
            $response = match ($outcome->status) {
                VoteOutcomeStatus::NotFriday => $this->refused('NOT_FRIDAY', Response::HTTP_CONFLICT),
                VoteOutcomeStatus::VoteClosed => $this->voteClosed(
                    $outcome->winner ?? throw new \LogicException('VOTE_CLOSED sans gagnant.'),
                ),
                VoteOutcomeStatus::AlreadyVoted => $this->refused('ALREADY_VOTED', Response::HTTP_CONFLICT),
                VoteOutcomeStatus::InvalidAccessory => $this->refused('INVALID_ACCESSORY', Response::HTTP_UNPROCESSABLE_ENTITY),
                VoteOutcomeStatus::Accepted => $this->accepted(
                    $outcome->accepted ?? throw new \LogicException('Issue « acceptée » sans détail.'),
                ),
            };
        }

        if ($resolvedVisitorCookie->issued instanceof Cookie) {
            $response->headers->setCookie($resolvedVisitorCookie->issued);
        }

        return $response;
    }

    private function readAccessoryCode(Request $request): ?string
    {
        $decoded = json_decode((string) $request->getContent(), true);
        if (!\is_array($decoded)) {
            return null;
        }

        $accessory = $decoded['accessory'] ?? null;
        if (!\is_string($accessory) || '' === trim($accessory)) {
            return null;
        }

        return trim($accessory);
    }

    private function accepted(AcceptedVote $acceptedVote): JsonResponse
    {
        return new JsonResponse([
            'accepted' => true,
            'accessory' => $acceptedVote->accessoryCode,
            'resultsSequence' => $acceptedVote->resultsSequence,
        ]);
    }

    private function voteClosed(AccessoryWinnerView $accessoryWinnerView): JsonResponse
    {
        // Format §10.6 : refus métier clair, avec de quoi MONTER le gagnant (§10.5).
        return new JsonResponse([
            'accepted' => false,
            'reason' => 'VOTE_CLOSED',
            'winner' => [
                'code' => $accessoryWinnerView->code,
                'label' => $accessoryWinnerView->label,
                'slot' => $accessoryWinnerView->slot,
                'svgGroupId' => $accessoryWinnerView->svgGroupId,
            ],
        ], Response::HTTP_CONFLICT);
    }

    private function refused(string $reason, int $status): JsonResponse
    {
        return new JsonResponse(['accepted' => false, 'reason' => $reason], $status);
    }
}
