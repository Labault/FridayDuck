<?php

declare(strict_types=1);

namespace App\Application\Coffee;

enum CoffeeOutcomeStatus
{
    case NotFriday;
    case LimitReached;
    case Served;
}
