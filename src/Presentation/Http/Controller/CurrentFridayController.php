<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Accessory\AccessoryOptionView;
use App\Application\Accessory\AccessoryWinnerView;
use App\Application\Accessory\VoteView;
use App\Application\Advice\AdviceView;
use App\Application\Friday\GetCurrentFridayHandler;
use App\Presentation\Http\Visitor\VisitorCookieResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/friday/current — état courant (§24.1).
 *
 * Point d'entrée fin : résout l'identité par cookie, délègue à l'Application
 * (qui résout/crée l'édition + options et trace la visite le vendredi),
 * sérialise. `active`/`status`/`vote.open`/`vote.closesAt` restent issus de
 * l'horloge ; énergie/cafés/résultats de l'édition persistée.
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
            'vote' => $this->voteBlock($currentFridayView->vote),
            'advice' => $this->adviceBlock($currentFridayView->advice),
            'visitor' => [
                'isNew' => $currentFridayView->visitorIsNew,
                'remainingCoffees' => $currentFridayView->remainingCoffees,
                'hasVoted' => $currentFridayView->visitorHasVoted,
                'votedAccessory' => $currentFridayView->votedAccessoryCode,
                'adviceReaction' => $currentFridayView->visitorAdviceReaction,
            ],
        ]);

        if ($resolvedVisitorCookie->issued instanceof Cookie) {
            $jsonResponse->headers->setCookie($resolvedVisitorCookie->issued);
        }

        return $jsonResponse;
    }

    /**
     * @return array{open: bool, closesAt: string, resultsSequence: int, winner: array{code: string, label: string, slot: string, svgGroupId: string}|null, options: list<array{code: string, label: string, displayOrder: int, voteCount: int}>}|null
     */
    private function voteBlock(?VoteView $voteView): ?array
    {
        if (!$voteView instanceof VoteView) {
            return null;
        }

        return [
            'open' => $voteView->open,
            'closesAt' => $voteView->closesAt,
            'resultsSequence' => $voteView->resultsSequence,
            'winner' => $this->winnerBlock($voteView->winner),
            'options' => array_map(
                static fn (AccessoryOptionView $accessoryOptionView): array => [
                    'code' => $accessoryOptionView->code,
                    'label' => $accessoryOptionView->label,
                    'displayOrder' => $accessoryOptionView->displayOrder,
                    'voteCount' => $accessoryOptionView->voteCount,
                ],
                $voteView->options,
            ),
        ];
    }

    /**
     * @return array{code: string, label: string, slot: string, svgGroupId: string}|null
     */
    private function winnerBlock(?AccessoryWinnerView $accessoryWinnerView): ?array
    {
        if (!$accessoryWinnerView instanceof AccessoryWinnerView) {
            return null;
        }

        return [
            'code' => $accessoryWinnerView->code,
            'label' => $accessoryWinnerView->label,
            'slot' => $accessoryWinnerView->slot,
            'svgGroupId' => $accessoryWinnerView->svgGroupId,
        ];
    }

    /**
     * @return array{text: string, slug: string, adviceSequence: int, reactions: array{CONCERNING: int, ALREADY_DONE: int, TAKING_NOTES: int}}|null
     */
    private function adviceBlock(?AdviceView $adviceView): ?array
    {
        if (!$adviceView instanceof AdviceView) {
            return null;
        }

        return [
            'text' => $adviceView->text,
            'slug' => $adviceView->slug,
            'adviceSequence' => $adviceView->adviceSequence,
            'reactions' => [
                'CONCERNING' => $adviceView->concerning,
                'ALREADY_DONE' => $adviceView->alreadyDone,
                'TAKING_NOTES' => $adviceView->takingNotes,
            ],
        ];
    }
}
