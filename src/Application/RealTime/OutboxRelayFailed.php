<?php

declare(strict_types=1);

namespace App\Application\RealTime;

/**
 * Au moins un événement n'a pu être publié pendant un cycle de relais. Lever cette
 * exception fait rejouer le message de relais (Messenger), puis le bascule en file
 * d'échec après seuil (§25.4, invariant E). Les lignes concernées restent non
 * publiées et seront reprises au prochain passage.
 */
final class OutboxRelayFailed extends \RuntimeException
{
}
