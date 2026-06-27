<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * Quota de café (§8.2) : au plus trois cafés par identité anonyme et par
 * vendredi.
 */
final class CoffeeQuota
{
    public const int MAX_PER_VISITOR = 3;
}
