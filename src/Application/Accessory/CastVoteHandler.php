<?php

declare(strict_types=1);

namespace App\Application\Accessory;

use App\Application\Friday\ResolveCurrentFridayEdition;
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
 * vote dans la transaction verrouillée — qui écrit aussi les résultats dans
 * l'outbox (§20.6, atomicité). Un relais les publiera.
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
        private AccessoryWinnerViewBuilder $accessoryWinnerViewBuilder,
        private CastVote $castVote,
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

        // Les résultats (ACCESSORY_RESULTS_UPDATED) sont désormais écrits dans
        // l'outbox PAR `cast()`, dans la transaction du vote (§20.6, invariant A) ;
        // un relais les publiera. Le handler n'a plus à diffuser.
        return VoteOutcome::accepted(new AcceptedVote($result->voteId, $accessory->code(), $result->resultsSequence));
    }
}
