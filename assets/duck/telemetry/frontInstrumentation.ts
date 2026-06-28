// Câblage de la télémétrie front de santé (§26.5) aux moments réels — fonctions
// PURES et testables, branchées dans les contrôleurs animation/stream. Toujours
// non bloquant et sans PII (l'émetteur sous-jacent l'est déjà).

import { HealthTelemetry } from './healthTelemetry.ts';

type AddsEventListener = {
  addEventListener(type: string, listener: () => void): void;
};

/** Mesure l'init Theatre.js : durée si succès, échec si elle lève (§26.5). */
export function measureAnimationInit(telemetry: HealthTelemetry, init: () => void, now: () => number = () => Date.now()): void {
  const start = now();
  try {
    init();
  } catch (error) {
    telemetry.animationInitFailure();
    throw error;
  }
  telemetry.animationInitDuration(Math.max(0, now() - start));
}

/** Exécute une séquence d'animation ; signale un échec de séquence (§26.5). */
export function runAnimationSequence(telemetry: HealthTelemetry, sequence: () => void): void {
  try {
    sequence();
  } catch (error) {
    telemetry.animationSequenceFailure();
    throw error;
  }
}

/**
 * Branche l'état de connexion Mercure : `open` → connected, `error` → disconnected
 * + incrément des reconnexions (EventSource retry nativement, §19.5).
 */
export function attachConnectionTelemetry(source: AddsEventListener, telemetry: HealthTelemetry): void {
  source.addEventListener('open', () => telemetry.mercureConnectionState('connected'));
  source.addEventListener('error', () => {
    telemetry.mercureConnectionState('disconnected');
    telemetry.mercureReconnect();
  });
}

/** Résout une cible SVG ; signale son absence (séquence non montable, §26.5). */
export function resolveSvgTarget(root: ParentNode, selector: string, telemetry: HealthTelemetry): Element | null {
  const target = root.querySelector(selector);
  if (target === null) {
    telemetry.svgMissingTarget(selector);
  }

  return target;
}
