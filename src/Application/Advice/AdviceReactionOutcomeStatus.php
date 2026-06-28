<?php

declare(strict_types=1);

namespace App\Application\Advice;

enum AdviceReactionOutcomeStatus
{
    case NotFriday;
    case InvalidReaction;
    case Recorded;
}
