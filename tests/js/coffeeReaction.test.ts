import { describe, expect, it } from 'vitest';
import { EnergyBarrier } from '../../assets/duck/state/energyBarrier.ts';
import { reactToCoffee } from '../../assets/duck/coffee/coffeeReaction.ts';

describe('reactToCoffee — animate only on real, advancing acceptance', () => {
  it('an accepted coffee advances energy, decrements remaining, plays coffee_receive', () => {
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 0, energyVersion: 0 });

    const reaction = reactToCoffee(
      { type: 'ACCEPTED', energy: 1, energyVersion: 1, remainingCoffees: 2, contributionId: 'C' },
      barrier,
    );

    expect(reaction.animate).toBe(true);
    expect(reaction.active).toBe(true);
    expect(reaction.remainingCoffees).toBe(2);
    expect(barrier.energy).toBe(1);
  });

  it('an idempotent replay never re-applies energy nor plays a second animation', () => {
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 1, energyVersion: 1 }); // l'acceptation initiale

    const reaction = reactToCoffee(
      { type: 'REPLAYED', energy: 1, energyVersion: 1, remainingCoffees: 2 },
      barrier,
    );

    expect(reaction.animate).toBe(false);
    expect(barrier.energy).toBe(1);
    expect(barrier.appliedVersion).toBe(1);
  });

  it('still animates my accepted coffee when the Mercure echo already pre-applied the state', () => {
    // Course Mercure-précède-POST : l'écho de MON café a déjà appliqué la v5 ;
    // ma réponse POST arrive ensuite. coffee_receive doit quand même jouer (mon
    // action délibérée) ; la barrière ne régresse pas. Le pulse global, lui, est
    // supprimé par la dédup (ActionLedger), d'où aucune double-animation.
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 5, energyVersion: 5 });

    const reaction = reactToCoffee(
      { type: 'ACCEPTED', energy: 5, energyVersion: 5, remainingCoffees: 1, contributionId: 'C' },
      barrier,
    );

    expect(reaction.animate).toBe(true);
    expect(barrier.energy).toBe(5);
  });

  it('reconciles NOT_FRIDAY to DORMANT with no animation (server is the clock authority)', () => {
    const reaction = reactToCoffee({ type: 'NOT_FRIDAY' }, new EnergyBarrier());

    expect(reaction.active).toBe(false);
    expect(reaction.animate).toBe(false);
    expect(reaction.message).not.toBeNull();
  });

  it('on quota reached (429): remaining 0 and a message, no animation', () => {
    const reaction = reactToCoffee({ type: 'LIMIT_REACHED' }, new EnergyBarrier());

    expect(reaction.remainingCoffees).toBe(0);
    expect(reaction.animate).toBe(false);
    expect(reaction.message).not.toBeNull();
  });
});
