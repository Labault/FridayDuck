<?php

declare(strict_types=1);

namespace App\Application\Advice;

use App\Domain\Advice\AdviceReaction;
use App\Domain\Advice\AdviceReactionRepository;
use App\Domain\Advice\AdviceReactionType;
use App\Domain\Friday\FridayEdition;
use App\Domain\Friday\FridayEditionRepository;
use App\Domain\Shared\Clock\ClockInterface;
use App\Domain\Shared\Identity\IdentifierGenerator;
use App\Domain\Shared\Persistence\Transactional;

/**
 * UPSERT de réaction dans une transaction atomique.
 *
 * Sous le VERROU d'édition (sérialise compteurs ET séquence, invariants C/D) :
 *  - aucune réaction → insert + incrément du compteur du type ;
 *  - réaction DIFFÉRENTE → update + décrément ancien + incrément nouveau (atomique) ;
 *  - MÊME réaction → no-op idempotent (rien touché, `changed = false`).
 */
final readonly class ReactToAdvice
{
    public function __construct(
        private Transactional $transactional,
        private FridayEditionRepository $fridayEditionRepository,
        private AdviceReactionRepository $adviceReactionRepository,
        private IdentifierGenerator $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function react(
        \DateTimeImmutable $fridayDate,
        string $timezone,
        string $visitorId,
        AdviceReactionType $adviceReactionType,
    ): AdviceReactionResult {
        return $this->transactional->transactional(function () use ($fridayDate, $timezone, $visitorId, $adviceReactionType): AdviceReactionResult {
            $edition = $this->fridayEditionRepository->findByFridayForUpdate($fridayDate, $timezone);
            if (!$edition instanceof FridayEdition) {
                throw new \RuntimeException('Édition introuvable au moment de réagir.');
            }

            $existing = $this->adviceReactionRepository->findByEditionAndVisitor($edition->id(), $visitorId);

            if (!$existing instanceof AdviceReaction) {
                $adviceReaction = AdviceReaction::record(
                    $this->identifierGenerator->nextIdentifier(),
                    $edition->id(),
                    $visitorId,
                    $adviceReactionType,
                    $this->clock->now(),
                );
                $this->adviceReactionRepository->add($adviceReaction);
                $edition->recordAdviceReaction($adviceReactionType);
                $this->fridayEditionRepository->save($edition);

                return $this->result(true, $adviceReactionType, $edition);
            }

            if ($existing->reaction() === $adviceReactionType) {
                return $this->result(false, $adviceReactionType, $edition); // no-op idempotent
            }

            $previous = $existing->reaction();
            $existing->changeTo($adviceReactionType, $this->clock->now());
            $this->adviceReactionRepository->save($existing);
            $edition->changeAdviceReaction($previous, $adviceReactionType);
            $this->fridayEditionRepository->save($edition);

            return $this->result(true, $adviceReactionType, $edition);
        });
    }

    private function result(bool $changed, AdviceReactionType $adviceReactionType, FridayEdition $fridayEdition): AdviceReactionResult
    {
        return new AdviceReactionResult(
            $changed,
            $adviceReactionType,
            $fridayEdition->adviceSequence(),
            $fridayEdition->concerningCount(),
            $fridayEdition->alreadyDoneCount(),
            $fridayEdition->takingNotesCount(),
        );
    }
}
