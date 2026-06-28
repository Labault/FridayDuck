<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * L'accessoire voté ne fait pas partie des trois options de l'édition (§24.3
 * INVALID_ACCESSORY) — code inconnu ou hors sélection du jour.
 */
final class InvalidAccessory extends \RuntimeException
{
}
