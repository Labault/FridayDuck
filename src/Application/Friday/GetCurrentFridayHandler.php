<?php

declare(strict_types=1);

namespace App\Application\Friday;

use App\Application\Accessory\AccessoryOptionsReader;
use App\Application\Accessory\AccessoryWinnerViewBuilder;
use App\Application\Accessory\ResolveAccessoryOptions;
use App\Application\Accessory\ResolveAccessoryWinner;
use App\Application\Accessory\VoteView;
use App\Application\Advice\AdviceView;
use App\Application\Advice\ResolveAdvice;
use App\Application\Visitor\RecordFridayVisit;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Accessory\Accessory;
use App\Domain\Accessory\AccessoryRepository;
use App\Domain\Accessory\AccessoryVote;
use App\Domain\Accessory\AccessoryVoteRepository;
use App\Domain\Accessory\AccessoryVoteSchedule;
use App\Domain\Advice\AdviceReaction;
use App\Domain\Advice\AdviceReactionRepository;
use App\Domain\Coffee\CoffeeContributionRepository;
use App\Domain\Coffee\CoffeeQuota;
use App\Domain\Friday\FridayCalendar;

/**
 * Orchestration de la lecture de l'état courant.
 *
 * L'autorité temporelle reste le Domaine de la Phase 1 (FridayCalendar) : c'est
 * lui qui dit « quel vendredi » et AWAKE/DORMANT. La base ne fournit QUE l'état
 * collectif persisté. L'identité du visiteur est toujours résolue ; l'édition
 * n'est résolue-ou-créée (et la visite tracée) QUE le vendredi (§25.2).
 */
final readonly class GetCurrentFridayHandler
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private ResolveCurrentFridayEdition $resolveCurrentFridayEdition,
        private ResolveAnonymousVisitor $resolveAnonymousVisitor,
        private RecordFridayVisit $recordFridayVisit,
        private CoffeeContributionRepository $coffeeContributionRepository,
        private AccessoryVoteSchedule $accessoryVoteSchedule,
        private ResolveAccessoryOptions $resolveAccessoryOptions,
        private ResolveAccessoryWinner $resolveAccessoryWinner,
        private AccessoryOptionsReader $accessoryOptionsReader,
        private AccessoryWinnerViewBuilder $accessoryWinnerViewBuilder,
        private AccessoryRepository $accessoryRepository,
        private AccessoryVoteRepository $accessoryVoteRepository,
        private ResolveAdvice $resolveAdvice,
        private AdviceReactionRepository $adviceReactionRepository,
    ) {
    }

    public function __invoke(string $visitorHash): CurrentFridayView
    {
        $fridayState = $this->fridayCalendar->currentState();
        $visitorResolution = $this->resolveAnonymousVisitor->resolve($visitorHash);

        $energy = 0;
        $energyVersion = 0;
        $coffeeCount = 0;
        $overcaffeinationCount = 0;
        $remainingCoffees = CoffeeQuota::MAX_PER_VISITOR;
        $vote = null;
        $visitorHasVoted = false;
        $votedAccessoryCode = null;
        $advice = null;
        $visitorAdviceReaction = null;

        if ($fridayState->active) {
            $edition = $this->resolveCurrentFridayEdition->resolve($fridayState->fridayDate, $fridayState->timezoneName());
            $this->recordFridayVisit->record($edition->id(), $visitorResolution->visitor->id());

            $energy = $edition->energy();
            $energyVersion = $edition->energyVersion();
            $coffeeCount = $edition->coffeeCount();
            $overcaffeinationCount = $edition->overcaffeinationCount();

            $served = $this->coffeeContributionRepository->countForVisitorAndEdition($edition->id(), $visitorResolution->visitor->id());
            $remainingCoffees = max(0, CoffeeQuota::MAX_PER_VISITOR - $served);

            // Bloc vote (§10) : options résolues-ou-créées, fenêtre dérivée de l'horloge.
            $this->resolveAccessoryOptions->resolve($edition->id(), $fridayState->fridayDate);
            $voteOpen = $this->accessoryVoteSchedule->isOpen($fridayState->fridayDate);

            $existingVote = $this->accessoryVoteRepository->findByEditionAndVisitor($edition->id(), $visitorResolution->visitor->id());
            $visitorHasVoted = $existingVote instanceof AccessoryVote;
            if ($existingVote instanceof AccessoryVote) {
                // Le choix du visiteur (pour verrouiller l'UI sur son option au rechargement).
                $votedAccessory = $this->accessoryRepository->findByIds([$existingVote->accessoryId()])[0] ?? null;
                $votedAccessoryCode = $votedAccessory instanceof Accessory ? $votedAccessory->code() : null;
            }

            $winner = null;
            if (!$voteOpen) {
                // Après 14:00 : fige le gagnant et l'expose (§10.6). Résolution
                // SILENCIEUSE (réparation §25.2) : l'annonce ACCESSORY_WINNER_SELECTED
                // est émise UNE fois par le Scheduler/rattrapage (Phase 6a, invariant E).
                $resolution = $this->resolveAccessoryWinner->resolve($fridayState->fridayDate, $fridayState->timezoneName());
                $winner = $this->accessoryWinnerViewBuilder->fromCode($resolution->winnerCode);
            }

            $vote = new VoteView(
                open: $voteOpen,
                closesAt: $this->accessoryVoteSchedule->closesAt($fridayState->fridayDate)->format(\DateTimeInterface::ATOM),
                winner: $winner,
                resultsSequence: $edition->resultsSequence(),
                options: $this->accessoryOptionsReader->forEdition($edition->id()),
            );

            // Bloc conseil (§11) : conseil du jour figé + compteurs de réactions.
            $adviceOfTheDay = $this->resolveAdvice->resolve($fridayState->fridayDate, $fridayState->timezoneName());
            $advice = new AdviceView(
                text: $adviceOfTheDay->text(),
                slug: $adviceOfTheDay->slug(),
                adviceSequence: $edition->adviceSequence(),
                concerning: $edition->concerningCount(),
                alreadyDone: $edition->alreadyDoneCount(),
                takingNotes: $edition->takingNotesCount(),
            );

            $reaction = $this->adviceReactionRepository->findByEditionAndVisitor($edition->id(), $visitorResolution->visitor->id());
            $visitorAdviceReaction = $reaction instanceof AdviceReaction ? $reaction->reaction()->value : null;
        }

        return new CurrentFridayView(
            active: $fridayState->active,
            date: $fridayState->date(),
            timezone: $fridayState->timezoneName(),
            status: $fridayState->status->value,
            energy: $energy,
            energyVersion: $energyVersion,
            coffeeCount: $coffeeCount,
            overcaffeinationCount: $overcaffeinationCount,
            visitorIsNew: $visitorResolution->isNew,
            remainingCoffees: $remainingCoffees,
            vote: $vote,
            visitorHasVoted: $visitorHasVoted,
            votedAccessoryCode: $votedAccessoryCode,
            advice: $advice,
            visitorAdviceReaction: $visitorAdviceReaction,
        );
    }
}
