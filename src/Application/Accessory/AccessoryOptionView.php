<?php

declare(strict_types=1);

namespace App\Application\Accessory;

/**
 * Projection d'une option de vote (§10.3, §24.1) : identité publique (code,
 * label), ordre d'affichage et compteur GLOBAL de votes.
 */
final readonly class AccessoryOptionView
{
    public function __construct(
        public string $code,
        public string $label,
        public int $displayOrder,
        public int $voteCount,
    ) {
    }
}
