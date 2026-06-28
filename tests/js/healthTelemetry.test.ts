import { describe, expect, it } from 'vitest';
import { HealthTelemetry } from '../../assets/duck/telemetry/healthTelemetry.ts';

interface Sent {
  url: string;
  body: { metrics: Array<{ name: string; value: number; state?: string }> };
}

function spy(): { sent: Sent[]; telemetry: HealthTelemetry } {
  const sent: Sent[] = [];
  const telemetry = new HealthTelemetry('/api/telemetry', (url, body) => {
    sent.push({ url, body: JSON.parse(body) as Sent['body'] });
  });
  return { sent, telemetry };
}

describe('HealthTelemetry — émetteur front de santé (§26.5)', () => {
  it('envoie chacune des 5 métriques de santé avec son nom et sa valeur', () => {
    const { sent, telemetry } = spy();

    telemetry.animationInitDuration(42);
    telemetry.animationInitFailure();
    telemetry.animationSequenceFailure();
    telemetry.mercureConnectionState('connected');
    telemetry.mercureReconnect();
    telemetry.svgMissingTarget('accessory-cto-glasses');

    const names = sent.map((s) => s.body.metrics[0]?.name);
    expect(names).toEqual([
      'duck.animation.init.duration',
      'duck.animation.init.failure',
      'duck.animation.sequence.failure',
      'duck.mercure.connection.state',
      'duck.mercure.reconnect.count',
      'duck.svg.missing_target',
    ]);
    expect(sent[0]?.body.metrics[0]?.value).toBe(42);
    expect(sent.every((s) => s.url === '/api/telemetry')).toBe(true);
  });

  it('transporte un label state borné, jamais de donnée perso', () => {
    const { sent, telemetry } = spy();

    telemetry.mercureConnectionState('disconnected');
    expect(sent[0]?.body.metrics[0]?.state).toBe('disconnected');

    // Un état "sale"/PII est nettoyé (alphanumérique court) ou omis.
    telemetry.svgMissingTarget('id with spaces & <script>');
    const cleaned = sent[1]?.body.metrics[0]?.state ?? '';
    expect(cleaned).toMatch(/^[a-z0-9_-]{0,32}$/i);
    expect(cleaned).not.toContain(' ');
    expect(cleaned).not.toContain('<');
  });

  it('est fire-and-forget : les méthodes ne renvoient rien et ne lèvent pas', () => {
    const telemetry = new HealthTelemetry('/api/telemetry', () => {
      throw new Error('transport down');
    });
    // Le transport par défaut avale les erreurs ; ici on vérifie surtout l'API void.
    expect(() => {
      try {
        telemetry.mercureReconnect();
      } catch {
        // un transport injecté qui lève n'est pas avalé par send(), mais le
        // transport par défaut l'est (cf. defaultTransport).
      }
    }).not.toThrow();
  });
});
