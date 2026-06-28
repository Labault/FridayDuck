/**
 * Modèle visuel piloté (§17.4) et résolution énergie → cibles (§17.5).
 *
 * IMPORTANT (§15.5) : ces fonctions ne décident RIEN. Elles convertissent une
 * énergie DÉJÀ validée par le serveur (0..100) en cibles d'animation. Aucune
 * règle métier ici.
 */

export type DuckVisualProperties = {
  energy: number;
  bodyY: number;
  bodyRotation: number;
  headRotation: number;
  eyeOpen: number;
  blinkSpeed: number;
  wingActivity: number;
  steamOpacity: number;
  steamSpeed: number;
  shakeIntensity: number;
  keyboardVisible: boolean;
  terminalVisible: boolean;
  incidentParticles: number;
};

/** Interpolation linéaire de `from` vers `to` selon `t` ∈ [0, 1]. */
export function interpolate(from: number, to: number, t: number): number {
  return from + (to - from) * t;
}

/** Accélération/décélération douce (smoothstep) sur `t` ∈ [0, 1]. */
export function easeInOut(t: number): number {
  const clamped = Math.max(0, Math.min(1, t));
  return clamped * clamped * (3 - 2 * clamped);
}

/**
 * Convertit une énergie validée en {@link DuckVisualProperties} — copie EXACTE
 * de la référence §17.5 (mêmes interpolations, mêmes seuils discrets :
 * clavier ≥ 51, terminal ≥ 71, tremblement & particules ≥ 91).
 */
export function resolveVisualTargets(energy: number): DuckVisualProperties {
  const normalized = Math.max(0, Math.min(100, energy)) / 100;

  return {
    energy,
    bodyY: interpolate(10, 0, normalized),
    bodyRotation: interpolate(8, -1, normalized),
    headRotation: interpolate(14, -2, normalized),
    eyeOpen: easeInOut(normalized),
    blinkSpeed: interpolate(0.4, 2.4, normalized),
    wingActivity: interpolate(0.1, 1.8, normalized),
    steamOpacity: interpolate(0.05, 1, normalized),
    steamSpeed: interpolate(0.2, 2.2, normalized),
    shakeIntensity: energy >= 91 ? (energy - 90) / 10 : 0,
    keyboardVisible: energy >= 51,
    terminalVisible: energy >= 71,
    incidentParticles: energy >= 91 ? Math.round((energy - 90) * 2) : 0,
  };
}
