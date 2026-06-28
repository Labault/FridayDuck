import { EnergyBarrier } from '../state/energyBarrier.ts';
import type { CoffeeApiOutcome } from './coffeeClient.ts';

export type CoffeeReaction = {
  /** Jouer coffee_receive (§18.2). */
  animate: boolean;
  /** Vendredi actif ? false si le serveur réconcilie vers DORMANT (NOT_FRIDAY). */
  active: boolean;
  /** Cafés restants (autorité serveur) ; null si l'issue n'en porte pas. */
  remainingCoffees: number | null;
  /** Message utilisateur ; null si rien à dire. */
  message: string | null;
};

/**
 * Décide la réaction du front à une issue de café, en faisant passer l'éventuel
 * état par la barrière de version.
 *
 * coffee_receive ne se joue QUE sur une acceptation RÉELLE dont l'état a
 * effectivement avancé (barrière) — jamais sur un rejeu idempotent (pas de
 * double-comptage visuel), jamais sur un état périmé. NOT_FRIDAY : le serveur
 * (autorité horloge) prime → réconciliation vers DORMANT.
 */
export function reactToCoffee(outcome: CoffeeApiOutcome, barrier: EnergyBarrier): CoffeeReaction {
  switch (outcome.type) {
    case 'ACCEPTED': {
      // Mon café accepté joue TOUJOURS coffee_receive (action délibérée). L'état
      // passe par la barrière (idempotent si l'écho Mercure l'a déjà appliqué, §18.3) ;
      // la dédup (ActionLedger) empêche le pulse global de doubler l'animation (D).
      barrier.apply(outcome);
      return { animate: true, active: true, remainingCoffees: outcome.remainingCoffees, message: null };
    }
    case 'REPLAYED': {
      // Met à jour si la version est plus récente, mais JAMAIS d'animation.
      barrier.apply(outcome);
      return { animate: false, active: true, remainingCoffees: outcome.remainingCoffees, message: null };
    }
    case 'NOT_FRIDAY':
      return {
        animate: false,
        active: false,
        remainingCoffees: null,
        message: 'Le canard ne sert le café que le vendredi.',
      };
    case 'LIMIT_REACHED':
      return {
        animate: false,
        active: true,
        remainingCoffees: 0,
        message: 'Quota de trois cafés atteint pour ce vendredi.',
      };
    case 'INVALID_KEY':
      return {
        animate: false,
        active: true,
        remainingCoffees: null,
        message: 'Erreur technique : clé d’idempotence manquante.',
      };
    case 'NETWORK_ERROR':
      return {
        animate: false,
        active: true,
        remainingCoffees: null,
        message: 'Réseau indisponible — réessayez.',
      };
  }
}
