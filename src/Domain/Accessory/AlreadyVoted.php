<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Le visiteur a déjà voté pour cette édition (§10.1, §24.3 ALREADY_VOTED). Levée
 * sous le verrou d'édition ou sur violation d'UNIQUE(edition, visitor).
 */
final class AlreadyVoted extends \RuntimeException
{
}
