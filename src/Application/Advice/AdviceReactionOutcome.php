<?php

declare(strict_types=1);

namespace App\Application\Advice;

/**
 * Issue d'une tentative de réaction (§24.4), projetée en HTTP. Un no-op
 * idempotent reste un succès (Recorded avec `result->changed = false`).
 */
final readonly class AdviceReactionOutcome
{
    private function __construct(
        public AdviceReactionOutcomeStatus $status,
        public ?AdviceReactionResult $result,
    ) {
    }

    public static function notFriday(): self
    {
        return new self(AdviceReactionOutcomeStatus::NotFriday, null);
    }

    public static function invalidReaction(): self
    {
        return new self(AdviceReactionOutcomeStatus::InvalidReaction, null);
    }

    public static function recorded(AdviceReactionResult $adviceReactionResult): self
    {
        return new self(AdviceReactionOutcomeStatus::Recorded, $adviceReactionResult);
    }
}
