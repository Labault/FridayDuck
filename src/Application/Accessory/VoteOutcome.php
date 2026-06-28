<?php

declare(strict_types=1);

namespace App\Application\Accessory;

/**
 * Issue d'une tentative de vote, projetée en HTTP par la Présentation (§24.3).
 * `winner` n'est porté que par VOTE_CLOSED (§10.6) ; `accepted` que par Accepted.
 */
final readonly class VoteOutcome
{
    private function __construct(
        public VoteOutcomeStatus $status,
        public ?AcceptedVote $accepted,
        public ?AccessoryWinnerView $winner,
    ) {
    }

    public static function notFriday(): self
    {
        return new self(VoteOutcomeStatus::NotFriday, null, null);
    }

    public static function voteClosed(AccessoryWinnerView $accessoryWinnerView): self
    {
        return new self(VoteOutcomeStatus::VoteClosed, null, $accessoryWinnerView);
    }

    public static function alreadyVoted(): self
    {
        return new self(VoteOutcomeStatus::AlreadyVoted, null, null);
    }

    public static function invalidAccessory(): self
    {
        return new self(VoteOutcomeStatus::InvalidAccessory, null, null);
    }

    public static function accepted(AcceptedVote $acceptedVote): self
    {
        return new self(VoteOutcomeStatus::Accepted, $acceptedVote, null);
    }
}
