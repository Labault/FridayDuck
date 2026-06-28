import { EnergyBarrier } from '../state/energyBarrier.ts';
import { ActionLedger } from './actionLedger.ts';
import { parseEnergyMessage } from './energyMessage.ts';

export type MercureRouting = {
  /** L'état a-t-il été appliqué (version qui avance) ? */
  applied: boolean;
  /** Jouer coffee_global_pulse (§18.3) ? */
  playPulse: boolean;
};

const IGNORED: MercureRouting = { applied: false, playPulse: false };

/**
 * Route un message Mercure brut (§19.4) :
 *  1. parse (malformé → ignoré) ;
 *  2. passe TOUJOURS par la barrière de version (état périmé → ignoré, invariant C) ;
 *  3. décide l'animation : coffee_global_pulse seulement si l'état a avancé ET
 *     que l'actionId n'est PAS le mien (mon propre café joue déjà coffee_receive
 *     via le POST — invariant D, dédup §18.3).
 *
 * Ne touche JAMAIS les cafés restants (invariant B) : aucun champ remaining ici.
 */
export function routeEnergyMessage(raw: string, barrier: EnergyBarrier, ledger: ActionLedger): MercureRouting {
  const message = parseEnergyMessage(raw);
  if (message === null) {
    return IGNORED;
  }

  const mine = ledger.isMine(message.actionId);
  const applied = barrier.apply({ energy: message.energy, energyVersion: message.energyVersion });

  return { applied, playPulse: applied && !mine };
}
