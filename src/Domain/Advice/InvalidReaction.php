<?php

declare(strict_types=1);

namespace App\Domain\Advice;

/**
 * Réaction hors des trois valeurs autorisées (§11.3, §24.4 INVALID_REACTION).
 */
final class InvalidReaction extends \RuntimeException
{
}
