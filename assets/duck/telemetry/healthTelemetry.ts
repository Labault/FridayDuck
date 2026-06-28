// Télémétrie front de SANTÉ (§26.5) — émetteur MINIMAL, non bloquant, zéro PII.
//
// POST fire-and-forget des 5 métriques de santé sur /api/telemetry (endpoint 7a,
// liste blanche côté serveur). `sendBeacon` quand dispo (n'impacte jamais le
// rendu, survit à l'unload) ; repli `fetch(keepalive)` silencieux. JAMAIS
// d'identité ni de comportement : valeur numérique + un label `state` borné.

/** Transport bas niveau : envoie un corps JSON sans bloquer. */
export type TelemetryTransport = (url: string, body: string) => void;

interface HealthMetric {
  readonly name: string;
  readonly value: number;
  readonly state?: string;
}

/** `state` : alphanumérique court (cardinalité bornée), sinon omis (anti-PII). */
function sanitizeState(state: string | undefined): string | undefined {
  if (state === undefined) {
    return undefined;
  }
  const cleaned = state.replace(/[^a-z0-9_-]/gi, '').slice(0, 32);
  return cleaned.length > 0 ? cleaned : undefined;
}

function defaultTransport(url: string, body: string): void {
  try {
    if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
      navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
      return;
    }
    if (typeof fetch === 'function') {
      // Fire-and-forget : on ignore la promesse ET les erreurs (santé best-effort).
      void fetch(url, { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(() => {});
    }
  } catch {
    // La télémétrie ne doit JAMAIS casser le rendu.
  }
}

export class HealthTelemetry {
  constructor(
    private readonly endpoint: string = '/api/telemetry',
    private readonly transport: TelemetryTransport = defaultTransport,
  ) {}

  animationInitDuration(durationMs: number): void {
    this.send({ name: 'duck.animation.init.duration', value: durationMs });
  }

  animationInitFailure(): void {
    this.send({ name: 'duck.animation.init.failure', value: 1 });
  }

  animationSequenceFailure(): void {
    this.send({ name: 'duck.animation.sequence.failure', value: 1 });
  }

  mercureConnectionState(state: 'connected' | 'connecting' | 'disconnected'): void {
    this.send({ name: 'duck.mercure.connection.state', value: 1, state });
  }

  mercureReconnect(): void {
    this.send({ name: 'duck.mercure.reconnect.count', value: 1 });
  }

  svgMissingTarget(target: string): void {
    this.send({ name: 'duck.svg.missing_target', value: 1, state: target });
  }

  private send(metric: HealthMetric): void {
    const state = sanitizeState(metric.state);
    const entry = state === undefined ? { name: metric.name, value: metric.value } : { name: metric.name, value: metric.value, state };
    this.transport(this.endpoint, JSON.stringify({ metrics: [entry] }));
  }
}
