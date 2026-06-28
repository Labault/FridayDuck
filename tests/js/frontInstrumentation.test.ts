import { describe, expect, it } from 'vitest';
import { HealthTelemetry } from '../../assets/duck/telemetry/healthTelemetry.ts';
import { attachConnectionTelemetry, measureAnimationInit, resolveSvgTarget, runAnimationSequence } from '../../assets/duck/telemetry/frontInstrumentation.ts';

interface Sent {
  name: string;
  value: number;
  state?: string;
}

function spy(): { sent: Sent[]; telemetry: HealthTelemetry } {
  const sent: Sent[] = [];
  const telemetry = new HealthTelemetry('/api/telemetry', (_url, body) => {
    const metric = (JSON.parse(body) as { metrics: Sent[] }).metrics[0];
    if (metric) {
      sent.push(metric);
    }
  });
  return { sent, telemetry };
}

describe('frontInstrumentation — câblage aux moments réels (§26.5)', () => {
  it('init OK → métrique de durée', () => {
    const { sent, telemetry } = spy();
    let t = 1000;
    measureAnimationInit(telemetry, () => {}, () => (t += 5));

    expect(sent.map((s) => s.name)).toEqual(['duck.animation.init.duration']);
    expect(sent[0]?.value).toBeGreaterThanOrEqual(0);
  });

  it('init qui lève → métrique d’échec (et relance)', () => {
    const { sent, telemetry } = spy();
    expect(() => measureAnimationInit(telemetry, () => { throw new Error('theatre down'); })).toThrow();
    expect(sent.map((s) => s.name)).toEqual(['duck.animation.init.failure']);
  });

  it('séquence qui lève → métrique d’échec de séquence', () => {
    const { sent, telemetry } = spy();
    expect(() => runAnimationSequence(telemetry, () => { throw new Error('seq'); })).toThrow();
    expect(sent.map((s) => s.name)).toEqual(['duck.animation.sequence.failure']);
  });

  it('reconnexion (error EventSource) → état déconnecté + compteur de reconnexion', () => {
    const { sent, telemetry } = spy();
    const listeners = new Map<string, () => void>();
    attachConnectionTelemetry({ addEventListener: (type, l) => listeners.set(type, l) }, telemetry);

    listeners.get('open')?.();
    listeners.get('error')?.();

    expect(sent.map((s) => s.name)).toEqual([
      'duck.mercure.connection.state',
      'duck.mercure.connection.state',
      'duck.mercure.reconnect.count',
    ]);
    expect(sent.map((s) => s.state)).toEqual(['connected', 'disconnected', undefined]);
  });

  it('cible SVG absente → métrique missing_target, présente → silence', () => {
    const { sent, telemetry } = spy();
    // Faux ParentNode (pas de DOM/jsdom requis) : '#present' existe, '#absent' non.
    const root = { querySelector: (selector: string) => (selector === '#present' ? ({} as Element) : null) } as unknown as ParentNode;

    expect(resolveSvgTarget(root, '#present', telemetry)).not.toBeNull();
    expect(resolveSvgTarget(root, '#absent', telemetry)).toBeNull();

    expect(sent.map((s) => s.name)).toEqual(['duck.svg.missing_target']);
  });
});
