<?php

declare(strict_types=1);

namespace App\Application\Accessory;

use App\Application\RealTime\AccessoryResultsUpdated;
use App\Application\RealTime\DomainEventPublisher;
use App\Application\Telemetry\Metrics;
use App\Application\Telemetry\Tracer;
use App\Domain\Accessory\Accessory;
use App\Domain\Accessory\AccessoryVote;
use App\Domain\Accessory\AccessoryVoteRepository;
use App\Domain\Accessory\AlreadyVoted;
use App\Domain\Accessory\FridayAccessoryOption;
use App\Domain\Accessory\FridayAccessoryOptionRepository;
use App\Domain\Accessory\InvalidAccessory;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\Transactional;

/**
 * Enregistre UN vote dans une transaction atomique.
 *
 * Ordre sous le VERROU d'édition (qui sérialise vote_count ET results_sequence,
 * invariant D) : déjà voté ? → ALREADY_VOTED ; accessoire hors options ? →
 * INVALID_ACCESSORY ; insert vote (UNIQUE(edition, visitor) = filet ultime) →
 * incrément du compteur d'option → bump de la séquence de résultats → commit.
 *
 * @throws AlreadyVoted
 * @throws InvalidAccessory
 */
final readonly class CastVote
{
    public function __construct(
        private Transactional $transactional,
        private FridayEditionRepository $fridayEditionRepository,
        private FridayAccessoryOptionRepository $fridayAccessoryOptionRepository,
        private AccessoryVoteRepository $accessoryVoteRepository,
        private IdentifierGenerator $identifierGenerator,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
        private AccessoryOptionsReader $accessoryOptionsReader,
        private Tracer $tracer,
        private Metrics $metrics,
    ) {
    }

    public function cast(
        \DateTimeImmutable $fridayDate,
        string $timezone,
        string $visitorId,
        Accessory $accessory,
    ): AccessoryVoteResult {
        return $this->tracer->trace('accessory.vote.cast', ['friday.date' => $fridayDate->format('Y-m-d')], function () use ($fridayDate, $timezone, $visitorId, $accessory): AccessoryVoteResult {
            $accessoryVoteResult = $this->castInTransaction($fridayDate, $timezone, $visitorId, $accessory);
            // Métrique métier à la mutation (§26.4), sur vote ACCEPTÉ uniquement.
            $this->metrics->counter('duck.accessory.vote.total');

            return $accessoryVoteResult;
        });
    }

    private function castInTransaction(
        \DateTimeImmutable $fridayDate,
        string $timezone,
        string $visitorId,
        Accessory $accessory,
    ): AccessoryVoteResult {
        return $this->transactional->transactional(function () use ($fridayDate, $timezone, $visitorId, $accessory): AccessoryVoteResult {
            $edition = $this->fridayEditionRepository->findByFridayForUpdate($fridayDate, $timezone);
            if (!$edition instanceof FridayEdition) {
                throw new \RuntimeException('Édition introuvable au moment de voter.');
            }

            // Un seul vote par visiteur (§23.6) : re-vote → ALREADY_VOTED, immuable.
            if ($this->accessoryVoteRepository->findByEditionAndVisitor($edition->id(), $visitorId) instanceof AccessoryVote) {
                throw new AlreadyVoted('Le visiteur a déjà voté pour cette édition.');
            }

            $fridayAccessoryOption = $this->matchOption($edition->id(), $accessory->id());

            $accessoryVote = AccessoryVote::cast(
                $this->identifierGenerator->nextIdentifier(),
                $edition->id(),
                $visitorId,
                $accessory->id(),
                $this->clock->now(),
            );
            $this->accessoryVoteRepository->add($accessoryVote);

            $fridayAccessoryOption->recordVote();
            $this->fridayAccessoryOptionRepository->save($fridayAccessoryOption);

            $edition->recordAccessoryVote();
            $this->fridayEditionRepository->save($edition);

            // Résultats diffusés via l'OUTBOX, DANS cette transaction (invariant A) :
            // le snapshot (compteurs à jour, options déjà flushées) et la séquence
            // anti-régression. La publication Mercure est différée au relais (6b).
            $this->domainEventPublisher->publish(
                $fridayDate,
                new AccessoryResultsUpdated($edition->resultsSequence(), $this->resultsSnapshot($edition->id())),
            );

            return new AccessoryVoteResult($accessoryVote->id(), $edition->resultsSequence());
        });
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

    private function matchOption(string $fridayEditionId, string $accessoryId): FridayAccessoryOption
    {
        foreach ($this->fridayAccessoryOptionRepository->findByEdition($fridayEditionId) as $fridayAccessoryOption) {
            if ($fridayAccessoryOption->accessoryId() === $accessoryId) {
                return $fridayAccessoryOption;
            }
        }

        throw new InvalidAccessory('Accessoire hors des options de l’édition.');
    }
}
