<?php

declare(strict_types=1);

namespace App\Application\Friday;

use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\ConcurrentCreationException;

/**
 * Résout l'édition d'un vendredi, ou la CRÉE à la volée (§25.2) — même sans
 * Scheduler, une requête le vendredi doit pouvoir ouvrir l'édition.
 *
 * Idempotent et race-safe : on s'appuie sur UNIQUE(friday_date, timezone). En
 * cas de course, l'insertion concurrente lève {@see ConcurrentCreationException}
 * et l'on relit la ligne gagnante (pas de check-then-insert naïf).
 */
final readonly class ResolveCurrentFridayEdition
{
    public function __construct(
        private FridayEditionRepository $fridayEditionRepository,
        private IdentifierGenerator $identifierGenerator,
        private ClockInterface $clock,
        private int $coffeeTarget,
    ) {
    }

    public function resolve(\DateTimeImmutable $fridayDate, string $timezone): FridayEdition
    {
        $existing = $this->fridayEditionRepository->findByFriday($fridayDate, $timezone);
        if ($existing instanceof FridayEdition) {
            return $existing;
        }

        $fridayEdition = FridayEdition::open(
            $this->identifierGenerator->nextIdentifier(),
            $fridayDate,
            $timezone,
            $this->coffeeTarget,
            $this->clock->now(),
        );

        try {
            $this->fridayEditionRepository->add($fridayEdition);

            return $fridayEdition;
        } catch (ConcurrentCreationException $exception) {
            $winner = $this->fridayEditionRepository->findByFriday($fridayDate, $timezone);
            if (!$winner instanceof FridayEdition) {
                throw new \RuntimeException('Édition introuvable après une création concurrente.', $exception->getCode(), previous: $exception);
            }

            return $winner;
        }
    }
}
