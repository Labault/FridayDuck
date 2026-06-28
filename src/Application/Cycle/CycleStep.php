<?php

declare(strict_types=1);

namespace App\Application\Cycle;

/**
 * Étapes du cycle d'un vendredi (§25.1). Chaque étape délègue, via
 * {@see FridayCycle}, à un service qui invoque les résolveurs EXISTANTS
 * (invariant A — zéro logique métier dupliquée).
 */
enum CycleStep
{
    case PrepareEdition;      // jeudi 23:55
    case PublishFridayOpened; // vendredi 00:00
    case CloseVote;           // vendredi 14:00
    case PublishWinner;       // vendredi 14:01
    case PrepareReport;       // vendredi 23:55
    case CloseFriday;         // samedi 00:00
    case GenerateReport;      // samedi 00:05
}
