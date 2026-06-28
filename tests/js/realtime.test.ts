import { describe, expect, it } from 'vitest';
import { EnergyBarrier } from '../../assets/duck/state/energyBarrier.ts';
import { parseEnergyMessage } from '../../assets/duck/realtime/energyMessage.ts';
import { ActionLedger } from '../../assets/duck/realtime/actionLedger.ts';
import { routeEnergyMessage } from '../../assets/duck/realtime/messageRouter.ts';

function message(energy: number, energyVersion: number, actionId: string): string {
  return JSON.stringify({ energy, energyVersion, actionId });
}

describe('parseEnergyMessage', () => {
  it('parses a well-formed global message (§20.3, invariant B)', () => {
    expect(parseEnergyMessage(message(7, 12, 'act-1'))).toEqual({ energy: 7, energyVersion: 12, actionId: 'act-1' });
  });

  it('returns null for malformed JSON or missing fields', () => {
    expect(parseEnergyMessage('not json')).toBeNull();
    expect(parseEnergyMessage(JSON.stringify({ energy: 7 }))).toBeNull();
    expect(parseEnergyMessage(JSON.stringify({ energy: '7', energyVersion: 1, actionId: 'a' }))).toBeNull();
  });
});

describe('ActionLedger — dedup of my own action-ids with TTL', () => {
  it('treats a key as mine while pending and for a TTL after settling, then forgets it', () => {
    let now = 1000;
    const ledger = new ActionLedger(5000, () => now);

    ledger.open('mine');
    expect(ledger.isMine('mine')).toBe(true); // en vol → mienne

    ledger.settle('mine'); // résultat terminal → expire à 6000
    now = 4000;
    expect(ledger.isMine('mine')).toBe(true); // encore dans le TTL
    now = 6001;
    expect(ledger.isMine('mine')).toBe(false); // TTL écoulé → oubliée
    expect(ledger.isMine('other')).toBe(false);
  });
});

describe('routeEnergyMessage — barrier + dedup (§19.4)', () => {
  it("plays coffee_global_pulse for ANOTHER visitor's advancing café", () => {
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 0, energyVersion: 0 });
    const ledger = new ActionLedger();

    const routing = routeEnergyMessage(message(1, 1, 'someone-else'), barrier, ledger);

    expect(routing.applied).toBe(true);
    expect(routing.playPulse).toBe(true);
    expect(barrier.energy).toBe(1);
  });

  it('NEVER pulses for the echo of my own café (dedup, invariant D)', () => {
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 0, energyVersion: 0 });
    const ledger = new ActionLedger();
    ledger.open('mine'); // j'ai émis ce café

    const routing = routeEnergyMessage(message(1, 1, 'mine'), barrier, ledger);

    expect(routing.applied).toBe(true); // l'état avance quand même
    expect(routing.playPulse).toBe(false); // mais pas de pulse (coffee_receive via POST)
  });

  it('IGNORES a message whose version is ≤ current (barrier), no pulse', () => {
    const barrier = new EnergyBarrier();
    barrier.apply({ energy: 5, energyVersion: 5 });
    const ledger = new ActionLedger();

    const routing = routeEnergyMessage(message(9, 5, 'other'), barrier, ledger);

    expect(routing.applied).toBe(false);
    expect(routing.playPulse).toBe(false);
    expect(barrier.energy).toBe(5); // inchangé
  });

  it('ignores a malformed message', () => {
    const routing = routeEnergyMessage('garbage', new EnergyBarrier(), new ActionLedger());
    expect(routing).toEqual({ applied: false, playPulse: false });
  });
});
