<?php

declare(strict_types=1);

namespace App\Application\Accessory;

use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\RealTime\AccessoryResultsUpdated;
use App\Application\RealTime\DomainEventPublisher;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Accessory\Accessory;
use App\Domain\Accessory\AccessoryRepository;
use App\Domain\Accessory\AccessoryVoteSchedule;
use App\Domain\Accessory\AlreadyVoted;
use App\Domain\Accessory\InvalidAccessory;
use App\Domain\Friday\FridayCalendar;

/**
 * Cas d'usage « voter pour un accessoire » (§24.3).
 *
 * GARDE TEMPORELLE EN PREMIER (invariant A) : hors vendredi → NOT_FRIDAY ; après
 * 14:00 → VOTE_CLOSED + gagnant résolu (§10.6), tous deux DÉRIVÉS DE L'HORLOGE.
 * Le vendredi avant 14:00 : résout identité/édition/options, valide l'accessoire,
 * vote dans la transaction verrouillée, puis publie POST-COMMIT (invariant E).
 */
final readonly class CastVoteHandler
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private AccessoryVoteSchedule $accessoryVoteSchedule,
        private ResolveAnonymousVisitor $resolveAnonymousVisitor,
        private ResolveCurrentFridayEdition $resolveCurrentFridayEdition,
        private ResolveAccessoryOptions $resolveAccessoryOptions,
        private ResolveAccessoryWinner $resolveAccessoryWinner,
        private AccessoryRepository $accessoryRepository,
        private AccessoryOptionsReader $accessoryOptionsReader,
        private AccessoryWinnerViewBuilder $accessoryWinnerViewBuilder,
        private CastVote $castVote,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function handle(string $visitorHash, string $accessoryCode): VoteOutcome
    {
        $fridayState = $this->fridayCalendar->currentState();
        if (!$fridayState->active) {
            return VoteOutcome::notFriday();
        }

        $fridayEdition = $this->resolveCurrentFridayEdition->resolve($fridayState->fridayDate, $fridayState->timezoneName());
        $this->resolveAccessoryOptions->resolve($fridayEdition->id(), $fridayState->fridayDate);

        // Vote fermé (horloge) → fige et renvoie le gagnant (§10.6).
        if (!$this->accessoryVoteSchedule->isOpen($fridayState->fridayDate)) {
            // Résolution SILENCIEUSE du gagnant (§10.6) : l'annonce ACCESSORY_WINNER_SELECTED
            // est émise UNE fois par le Scheduler/rattrapage (Phase 6a, invariant E), pas ici.
            $resolution = $this->resolveAccessoryWinner->resolve($fridayState->fridayDate, $fridayState->timezoneName());

            return VoteOutcome::voteClosed($this->accessoryWinnerViewBuilder->fromCode($resolution->winnerCode));
        }

        $accessory = $this->accessoryRepository->findByCode($accessoryCode);
        if (!$accessory instanceof Accessory) {
            return VoteOutcome::invalidAccessory();
        }

        $visitor = $this->resolveAnonymousVisitor->resolve($visitorHash)->visitor;

        try {
            $result = $this->castVote->cast($fridayState->fridayDate, $fridayState->timezoneName(), $visitor->id(), $accessory);
        } catch (AlreadyVoted) {
            return VoteOutcome::alreadyVoted();
        } catch (InvalidAccessory) {
            return VoteOutcome::invalidAccessory();
        }

        // POST-COMMIT (invariant E) : résultats diffusés, SEULEMENT sur vote accepté.
        $this->domainEventPublisher->publish(
            $fridayState->fridayDate,
            new AccessoryResultsUpdated($result->resultsSequence, $this->resultsSnapshot($fridayEdition->id())),
        );

        return VoteOutcome::accepted(new AcceptedVote($result->voteId, $accessory->code(), $result->resultsSequence));
    }

    /**
     * @return list<array{code: string, displayOrder: int, voteCount: int}>
     */
    private function resultsSnapshot(string $fridayEditionId): array
    {
        return array_map(
            static fn (AccessoryOptionView $accessoryOptionView): array => [
                'code' => $accessoryOptionView->code,
                'displayOrder' => $accessoryOptionView->displayOrder,
                'voteCount' => $accessoryOptionView->voteCount,
            ],
            $this->accessoryOptionsReader->forEdition($fridayEditionId),
        );
    }
}
