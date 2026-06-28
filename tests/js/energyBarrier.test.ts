import { describe, expect, it } from 'vitest';
import { EnergyBarrier } from '../../assets/duck/state/energyBarrier.ts';

describe('EnergyBarrier — the version barrier (§19, 2b-ii)', () => {
  it('applies the seeded initial state (any version beats the sentinel)', () => {
    const barrier = new EnergyBarrier();

    expect(barrier.apply({ energy: 5, energyVersion: 0 })).toBe(true);
    expect(barrier.energy).toBe(5);
    expect(barrier.appliedVersion).toBe(0);
  });

  it('IGNORES a state whose version is ≤ current (stale / out of order)', () => {
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 5, energyVersion: 3 });

    expect(barrier.apply({ energy: 99, energyVersion: 3 })).toBe(false); // equal → ignored
    expect(barrier.apply({ energy: 99, energyVersion: 2 })).toBe(false); // lower → ignored
    expect(barrier.energy).toBe(5);
    expect(barrier.appliedVersion).toBe(3);
  });

  it('applies a strictly higher version', () => {
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 5, energyVersion: 3 });

    expect(barrier.apply({ energy: 6, energyVersion: 4 })).toBe(true);
    expect(barrier.energy).toBe(6);
    expect(barrier.appliedVersion).toBe(4);
  });
});
