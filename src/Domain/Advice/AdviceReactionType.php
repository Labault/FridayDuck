<?php

declare(strict_types=1);

namespace App\Domain\Advice;

/**
 * Les TROIS réactions du MVP (§11.3). Un visiteur en choisit une seule par
 * vendredi, mais peut en CHANGER (réaction mutable, §23.8).
 */
enum AdviceReactionType: string
{
    case Concerning = 'CONCERNING';
    case AlreadyDone = 'ALREADY_DONE';
    case TakingNotes = 'TAKING_NOTES';
}
