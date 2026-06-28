<?php

declare(strict_types=1);

namespace App\Domain\Accessory;

/**
 * Emplacement d'un accessoire sur le canard (§10.5, §23.4). Détermine le groupe
 * SVG porteur côté front (4b) : tête, corps ou main.
 */
enum AccessorySlot: string
{
    case Head = 'head';
    case Body = 'body';
    case Hand = 'hand';
}
