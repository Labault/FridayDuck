<?php

declare(strict_types=1);

namespace App\Application\Cycle;

use App\Application\Accessory\AccessoryWinnerViewBuilder;
use App\Application\Accessory\ResolveAccessoryWinner;
use App\Application\RealTime\AccessoryWinnerSelected;
use App\Application\RealTime\DomainEventPublisher;
use App\Domain\Shared\Persistence\Transactional;

/**
 * Annonce le gagnant (§25.1, vendredi 14:01) : s'assure qu'il est résolu (4a,
 * silencieux) puis publie ACCESSORY_WINNER_SELECTED UNE FOIS (invariant E, dédup
 * par clé). Si une requête paresseuse l'a résolu entre 14:00 et 14:01, elle ne
 * l'a PAS annoncé — c'est ici (ou au rattrapage) que l'annonce sort, une fois.
 */
final readonly class PublishWinner
{
    public function __construct(
        private PrepareFridayEdition $prepareFridayEdition,
        private ResolveAccessoryWinner $resolveAccessoryWinner,
        private AccessoryWinnerViewBuilder $accessoryWinnerViewBuilder,
        private ProcessedMessageGuard $processedMessageGuard,
        private DomainEventPublisher $domainEventPublisher,
        private Transactional $transactional,
    ) {
    }

    public function publish(\DateTimeImmutable $fridayDate, string $timezone): void
    {
        $this->prepareFridayEdition->prepare($fridayDate, $timezone);
        $accessoryWinnerResolution = $this->resolveAccessoryWinner->resolve($fridayDate, $timezone);

        // §25.4 — Dédup + annonce atomiques (cf. OpenFriday) : sous async+retry, un
        // échec de publication rejoue proprement, exactement-une-fois préservé.
        $this->transactional->transactional(function () use ($fridayDate, $accessoryWinnerResolution): void {
            if ($this->processedMessageGuard->markIfFirst(CycleKey::accessoryWinner($fridayDate))) {
                $winner = $this->accessoryWinnerViewBuilder->fromCode($accessoryWinnerResolution->winnerCode);
                $this->domainEventPublisher->publish(
                    $fridayDate,
                    new AccessoryWinnerSelected($winner->code, $winner->label, $winner->slot, $winner->svgGroupId),
                );
            }
        });
    }
}
