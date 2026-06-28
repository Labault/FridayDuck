import type { DuckVisualProperties } from './visualProperties.ts';

/**
 * Signaux d'animation TEMPS (idle + café) calculés par la boucle/Theatre.js.
 * Tous normalisés ; le rig leur applique des échelles visuelles (pivots §16.4).
 */
export type DuckModulation = {
  breath: number; // -1..1, respiration lente
  blink: number; // 0..1, 1 = clignement (yeux momentanément fermés)
  wingPhase: number; // -1..1, oscillation des ailes
  shakeX: number; // -1..1, tremblement
  shakeY: number; // -1..1
  steamPulse: number; // 0..1, pulsation de la vapeur
  coffee: CoffeeOverlay;
  halo: number; // 0..1, intensité du halo du pulse global (§18.3)
  reveal: number; // 0..1, flourish transitoire de révélation du gagnant (§10.5)
  reducedMotion: boolean;
};

export type CoffeeOverlay = {
  active: boolean;
  headToCup: number; // 0..1, regard/penché vers la tasse
  beakOpen: number; // 0..1
  reaction: number; // -1..1, sursaut énergétique
};

export const NEUTRAL_COFFEE: CoffeeOverlay = { active: false, headToCup: 0, beakOpen: 0, reaction: 0 };

// Échelles visuelles : les valeurs §17.5 sont volontairement petites ; on les
// amplifie pour une démo lisible (la fonction §17.5 reste la source de vérité).
const BODY_Y_PX = 5.5;
const BREATH_PX = 7;
const HEAD_DEG = 1;
const WING_DEG = 26;
const SHAKE_PX = 9;
const COFFEE_HEAD_DEG = 16;
const BEAK_OPEN = 0.5;
const REVEAL_LIFT_PX = 18;

export class DuckRig {
  private readonly el: (id: string) => SVGGraphicsElement;

  constructor(root: ParentNode) {
    const cache = new Map<string, SVGGraphicsElement>();
    this.el = (id) => {
      const found = cache.get(id);
      if (found) {
        return found;
      }
      const node = root.querySelector<SVGGraphicsElement>(`#${id}`);
      if (!node) {
        throw new Error(`Groupe SVG introuvable : #${id}`);
      }
      cache.set(id, node);
      return node;
    };
  }

  /** Applique l'état visuel (cibles d'énergie + modulation temps) au SVG. */
  apply(base: DuckVisualProperties, m: DuckModulation): void {
    const still = m.reducedMotion;

    // ── Corps / posture (#duck-root) : bodyY, rotation, tremblement.
    const breath = still ? 0 : m.breath * BREATH_PX;
    const shake = still ? { x: 0, y: 0 } : {
      x: m.shakeX * base.shakeIntensity * SHAKE_PX,
      y: m.shakeY * base.shakeIntensity * SHAKE_PX,
    };
    // Révélation du gagnant (§10.5) : court bond vers le haut + léger zoom (« ta-da »).
    const revealLift = still ? 0 : m.reveal * REVEAL_LIFT_PX;
    const revealScale = still ? 0 : m.reveal * 0.08;
    const bodyTy = base.bodyY * BODY_Y_PX + breath + shake.y - revealLift;
    // Réaction de café (fin de coffee_receive / pulse global) : court « sursaut ».
    const perk = still ? 0 : m.coffee.reaction * 0.06;
    this.setTransform(
      'duck-root',
      `translate(${shake.x}px, ${bodyTy}px) rotate(${base.bodyRotation}deg) scale(${1 + perk + revealScale})`,
    );

    // ── Tête : inclinaison d'énergie + petit hochement + penché vers la tasse.
    const headBob = still ? 0 : m.breath * HEAD_DEG;
    const headRot = base.headRotation + headBob + m.coffee.headToCup * COFFEE_HEAD_DEG;
    this.setTransform('duck-head', `rotate(${headRot}deg)`);

    // ── Yeux : eyeOpen − clignement ; le second œil (droit) s'ouvre plus tard (§9).
    const effectiveOpen = still ? base.eyeOpen : base.eyeOpen * (1 - m.blink);
    const leftClosed = clamp01(1 - effectiveOpen);
    const rightOpen = clamp01((effectiveOpen - 0.12) / 0.88);
    const rightClosed = clamp01(1 - rightOpen);
    this.setTransform('duck-left-eyelid', `scaleY(${leftClosed})`);
    this.setTransform('duck-right-eyelid', `scaleY(${rightClosed})`);

    // ── Ailes : oscillation d'amplitude wingActivity (l'aile droite est miroir).
    const wing = still ? 0 : m.wingPhase * base.wingActivity * WING_DEG;
    this.setTransform('duck-left-wing', `rotate(${wing}deg)`);
    this.setTransform('duck-right-wing', `rotate(${-wing}deg)`);

    // ── Bec : ouverture pendant le café.
    this.setTransform('duck-beak', `scaleY(${1 + m.coffee.beakOpen * BEAK_OPEN})`);

    // ── Vapeur : opacité d'énergie, pulsée (steamSpeed) hors reduced-motion.
    const steam = still ? base.steamOpacity : base.steamOpacity * (0.55 + 0.45 * m.steamPulse);
    this.setOpacity('coffee-steam', steam);

    // ── Révélations discrètes : clavier (≥51), terminal (≥71), particules (≥91).
    this.setOpacity('keyboard', base.keyboardVisible ? 1 : 0);
    this.setOpacity('terminal', base.terminalVisible ? 1 : 0);
    this.setOpacity('particles', Math.min(1, base.incidentParticles / 20));

    // ── Halo (§18.3) : pulse global OU révélation du gagnant (le plus fort). Pas en reduced.
    const halo = still ? 0 : Math.max(m.halo, m.reveal);
    this.setOpacity('duck-halo', halo * 0.5);
    this.setTransform('duck-halo', `scale(${1 + halo * 0.18})`);

    // ── « Zzz » du sommeil : visibles quand l'énergie est au plus bas (dormant).
    // L'envol est animé en CSS ; on reste visible même en reduced-motion (indicateur d'état).
    this.setOpacity('duck-sleep', base.sleepZ);
  }

  private setTransform(id: string, value: string): void {
    this.el(id).style.transform = value;
  }

  private setOpacity(id: string, value: number): void {
    this.el(id).style.opacity = String(clamp01(value));
  }
}

function clamp01(v: number): number {
  return Math.max(0, Math.min(1, v));
}
