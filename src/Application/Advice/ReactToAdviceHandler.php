<?php

declare(strict_types=1);

namespace App\Application\Advice;

use App\Application\Friday\ResolveCurrentFridayEdition;
use App\Application\Visitor\ResolveAnonymousVisitor;
use App\Domain\Advice\AdviceReactionType;
use App\Domain\Friday\FridayCalendar;

/**
 * Cas d'usage « réagir au conseil » (§24.4).
 *
 * GARDE TEMPORELLE EN PREMIER (invariant A) : les réactions sont ouvertes TOUT le
 * vendredi (AWAKE) — pas de clôture à 14:00 (contrairement au vote). Hors vendredi
 * → NOT_FRIDAY. Réaction inconnue → INVALID_REACTION. Puis upsert transactionnel
 * qui écrit aussi l'événement dans l'outbox sur changement EFFECTIF (§20.6).
 */
final readonly class ReactToAdviceHandler
{
    public function __construct(
        private FridayCalendar $fridayCalendar,
        private ResolveAnonymousVisitor $resolveAnonymousVisitor,
        private ResolveCurrentFridayEdition $resolveCurrentFridayEdition,
        private ResolveAdvice $resolveAdvice,
        private ReactToAdvice $reactToAdvice,
    ) {
    }

    public function handle(string $visitorHash, string $reactionInput): AdviceReactionOutcome
    {
        $fridayState = $this->fridayCalendar->currentState();
        if (!$fridayState->active) {
            return AdviceReactionOutcome::notFriday();
        }

        $reaction = AdviceReactionType::tryFrom($reactionInput);
        if (!$reaction instanceof AdviceReactionType) {
            return AdviceReactionOutcome::invalidReaction();
        }

        $this->resolveCurrentFridayEdition->resolve($fridayState->fridayDate, $fridayState->timezoneName());
        $this->resolveAdvice->resolve($fridayState->fridayDate, $fridayState->timezoneName());
        $visitor = $this->resolveAnonymousVisitor->resolve($visitorHash)->visitor;

        $adviceReactionResult = $this->reactToAdvice->react($fridayState->fridayDate, $fridayState->timezoneName(), $visitor->id(), $reaction);

        // L'événement ADVICE_REACTION_CHANGED est désormais écrit dans l'outbox PAR
        // `react()`, dans sa transaction et seulement sur changement effectif (§20.6,
        // invariant A) ; un relais le publiera. Le handler n'a plus à diffuser.
        return AdviceReactionOutcome::recorded($adviceReactionResult);
    }
}
