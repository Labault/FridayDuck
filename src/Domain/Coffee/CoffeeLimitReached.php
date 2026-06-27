<?php

declare(strict_types=1);

namespace App\Domain\Coffee;

/**
 * Levée quand le visiteur a déjà servi son quota de cafés pour le vendredi
 * courant (§8.2). Traduite en COFFEE_LIMIT_REACHED côté HTTP (§24.2).
 */
final class CoffeeLimitReached extends \RuntimeException
{
}
