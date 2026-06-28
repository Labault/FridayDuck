<?php

declare(strict_types=1);

namespace App\Application\Accessory;

enum VoteOutcomeStatus
{
    case NotFriday;
    case VoteClosed;
    case AlreadyVoted;
    case InvalidAccessory;
    case Accepted;
}
