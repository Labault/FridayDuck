<?php

declare(strict_types=1);

namespace App\Application\Advice;

use App\Domain\Accessory\DateSeededOrdering;
use App\Domain\Advice\Advice;
use App\Domain\Advice\AdviceRepository;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Persistence\Transactional;

/**
 * Résout (ou fige paresseusement, §25.2) LE conseil du vendredi.
 *
 * Sélection DÉTERMINISTE seedée par la date (§10.4 réutilisé), persistée IMMUABLE
 * sur l'édition (advice_id) : un changement de catalogue en plein vendredi ne
 * déplace pas le conseil du jour (invariant B). Chemin rapide sans verrou une
 * fois figé ; création sous le verrou d'édition (race-safe), comme le gagnant.
 */
final readonly class ResolveAdvice
{
    public function __construct(
        private Transactional $transactional,
        private FridayEditionRepository $fridayEditionRepository,
        private AdviceRepository $adviceRepository,
    ) {
    }

    public function resolve(\DateTimeImmutable $fridayDate, string $timezone): Advice
    {
        $edition = $this->fridayEditionRepository->findByFriday($fridayDate, $timezone);
        if ($edition instanceof FridayEdition) {
            $existing = $this->loadAdvice($edition);
            if ($existing instanceof Advice) {
                return $existing;
            }
        }

        return $this->transactional->transactional(function () use ($fridayDate, $timezone): Advice {
            $edition = $this->fridayEditionRepository->findByFridayForUpdate($fridayDate, $timezone);
            if (!$edition instanceof FridayEdition) {
                throw new \RuntimeException('Édition introuvable pour la résolution du conseil.');
            }

            $existing = $this->loadAdvice($edition);
            if ($existing instanceof Advice) {
                return $existing;
            }

            $advice = $this->select($fridayDate);
            $edition->selectAdvice($advice->id());
            $this->fridayEditionRepository->save($edition);

            return $advice;
        });
    }

    private function loadAdvice(FridayEdition $fridayEdition): ?Advice
    {
        $adviceId = $fridayEdition->adviceId();

        return null === $adviceId ? null : $this->adviceRepository->findById($adviceId);
    }

    private function select(\DateTimeImmutable $fridayDate): Advice
    {
        $ordered = DateSeededOrdering::order(
            $fridayDate->format('Y-m-d'),
            $this->adviceRepository->findActive(),
            static fn (Advice $advice): string => $advice->slug(),
        );

        return $ordered[0] ?? throw new \RuntimeException('Catalogue de conseils vide.');
    }
}
