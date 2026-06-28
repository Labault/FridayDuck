<?php

declare(strict_types=1);

namespace App\Application\Accessory;

use App\Domain\Accessory\AccessoryWinnerCalculator;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Persistence\Transactional;

/**
 * Résout-ou-CRÉE le gagnant après 14:00 (§10.4, §25.2), paresseusement et
 * race-safe — sans Scheduler, c'est la 1re requête après clôture qui le fige.
 *
 * Sous le verrou d'édition : si le gagnant est déjà persisté, on le renvoie
 * (immuable, §10.1) ; sinon on le calcule depuis les compteurs (départage
 * déterministe), on le persiste et on signale `justResolved` pour la publication
 * POST-COMMIT. Deux requêtes concurrentes → un seul gagnant (le verrou sérialise).
 *
 * Pré-requis : les options de l'édition existent (résolues en amont) — la 1re
 * requête après 14:00 sans aucun vote produit quand même un gagnant déterministe.
 */
final readonly class ResolveAccessoryWinner
{
    public function __construct(
        private Transactional $transactional,
        private FridayEditionRepository $fridayEditionRepository,
        private AccessoryOptionsReader $accessoryOptionsReader,
    ) {
    }

    public function resolve(\DateTimeImmutable $fridayDate, string $timezone): AccessoryWinnerResolution
    {
        return $this->transactional->transactional(function () use ($fridayDate, $timezone): AccessoryWinnerResolution {
            $edition = $this->fridayEditionRepository->findByFridayForUpdate($fridayDate, $timezone);
            if (!$edition instanceof FridayEdition) {
                throw new \RuntimeException('Édition introuvable pour la résolution du gagnant.');
            }

            $current = $edition->winnerAccessoryCode();
            if (null !== $current) {
                return new AccessoryWinnerResolution($current, false);
            }

            $tally = array_map(
                static fn (AccessoryOptionView $accessoryOptionView): array => ['code' => $accessoryOptionView->code, 'voteCount' => $accessoryOptionView->voteCount],
                $this->accessoryOptionsReader->forEdition($edition->id()),
            );
            if ([] === $tally) {
                throw new \RuntimeException('Aucune option à départager pour la résolution du gagnant.');
            }

            $winner = AccessoryWinnerCalculator::decide($fridayDate->format('Y-m-d'), $tally);
            $edition->selectWinner($winner);
            $this->fridayEditionRepository->save($edition);

            return new AccessoryWinnerResolution($winner, true);
        });
    }
}
