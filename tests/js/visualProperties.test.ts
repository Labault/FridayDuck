import { describe, expect, it } from 'vitest';
import { resolveVisualTargets } from '../../assets/duck/visualProperties.ts';
import { energyState } from '../../assets/duck/energyState.ts';

describe('resolveVisualTargets (§17.5)', () => {
  it('clamps energy and produces the slumped, eyes-shut COMATOSE pose at 0', () => {
    const t = resolveVisualTargets(0);
    expect(t.bodyY).toBe(10);
    expect(t.bodyRotation).toBe(8);
    expect(t.headRotation).toBe(14);
    expect(t.eyeOpen).toBe(0);
    expect(t.keyboardVisible).toBe(false);
    expect(t.terminalVisible).toBe(false);
    expect(t.shakeIntensity).toBe(0);
    expect(t.incidentParticles).toBe(0);
    // Endormi à plat : les « Zzz » sont au maximum.
    expect(t.sleepZ).toBe(1);
  });

  it('produces the upright, wide-awake pose at 100', () => {
    const t = resolveVisualTargets(100);
    expect(t.bodyY).toBe(0);
    expect(t.bodyRotation).toBe(-1);
    expect(t.headRotation).toBe(-2);
    expect(t.eyeOpen).toBe(1);
    expect(t.blinkSpeed).toBeCloseTo(2.4, 6);
    expect(t.wingActivity).toBeCloseTo(1.8, 6);
    expect(t.steamOpacity).toBe(1);
    // Bien réveillé : plus aucun « Zzz ».
    expect(t.sleepZ).toBe(0);
  });

  it('fades the sleeping Zzz out by the time the duck stirs (energy ≥ 24)', () => {
    expect(resolveVisualTargets(0).sleepZ).toBe(1);
    expect(resolveVisualTargets(12).sleepZ).toBeCloseTo(0.5, 6);
    expect(resolveVisualTargets(24).sleepZ).toBe(0);
    expect(resolveVisualTargets(40).sleepZ).toBe(0);
  });

  it('clamps out-of-range energy like the reference', () => {
    expect(resolveVisualTargets(-50).bodyY).toBe(10);
    expect(resolveVisualTargets(250).bodyY).toBe(0);
  });

  it.each([
    [50, false],
    [51, true],
    [70, true],
  ])('keyboard appears at the 51 threshold (energy %i -> %s)', (energy, visible) => {
    expect(resolveVisualTargets(energy).keyboardVisible).toBe(visible);
  });

  it.each([
    [70, false],
    [71, true],
    [90, true],
  ])('terminal appears at the 71 threshold (energy %i -> %s)', (energy, visible) => {
    expect(resolveVisualTargets(energy).terminalVisible).toBe(visible);
  });

  it('triggers shake and particles only from 91 (CAFFEINE_INCIDENT)', () => {
    expect(resolveVisualTargets(90).shakeIntensity).toBe(0);
    expect(resolveVisualTargets(90).incidentParticles).toBe(0);

    expect(resolveVisualTargets(91).shakeIntensity).toBeCloseTo(0.1, 6);
    expect(resolveVisualTargets(91).incidentParticles).toBe(2);

    expect(resolveVisualTargets(100).shakeIntensity).toBeCloseTo(1, 6);
    expect(resolveVisualTargets(100).incidentParticles).toBe(20);
  });
});

describe('energyState (§9.1)', () => {
  it.each([
    [0, 'COMATOSE'],
    [10, 'COMATOSE'],
    [11, 'PHYSICALLY_PRESENT'],
    [30, 'PHYSICALLY_PRESENT'],
    [31, 'BARELY_FUNCTIONAL'],
    [50, 'BARELY_FUNCTIONAL'],
    [51, 'PERFORMING_PRODUCTIVITY'],
    [70, 'PERFORMING_PRODUCTIVITY'],
    [71, 'OVERMOTIVATED'],
    [90, 'OVERMOTIVATED'],
    [91, 'CAFFEINE_INCIDENT'],
    [100, 'CAFFEINE_INCIDENT'],
  ])('maps energy %i to %s', (energy, code) => {
    expect(energyState(energy).code).toBe(code);
  });
});
