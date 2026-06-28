import { resolveVisualTargets } from '../visualProperties.ts';
import { DuckRig, NEUTRAL_COFFEE, type CoffeeOverlay } from '../duckRig.ts';
import type { DuckTheatre } from '../theatre/project.ts';

/**
 * Animation de café `coffee_receive` (§18.2). Tween à durée bornée : interrompt
 * l'idle, se joue, puis se résorbe (overlay → 0). Comme la base est recalculée
 * à CHAQUE frame depuis l'énergie courante, le retour se fait toujours vers
 * l'état courant — jamais vers une ancienne valeur (§17.7).
 */
export class CoffeeController {
  private startedAt: number | null = null;
  private durationMs = 1800;

  trigger(now: number, durationMs: number): void {
    this.startedAt = now;
    this.durationMs = durationMs;
  }

  active(): boolean {
    return this.startedAt !== null;
  }

  sample(now: number): CoffeeOverlay {
    if (this.startedAt === null) {
      return NEUTRAL_COFFEE;
    }
    const p = (now - this.startedAt) / this.durationMs;
    if (p >= 1) {
      this.startedAt = null;
      return NEUTRAL_COFFEE;
    }
    // 0–0.25 : regard vers la tasse ; 0.25–0.7 : boit (bec ouvert) ; 0.7–1 : réaction.
    const headToCup = p < 0.7 ? smooth(Math.min(1, p / 0.25)) : smooth(1 - (p - 0.7) / 0.3);
    const beakOpen = p > 0.25 && p < 0.7 ? Math.sin(((p - 0.25) / 0.45) * Math.PI) : 0;
    const reaction = p > 0.7 ? Math.sin(((p - 0.7) / 0.3) * Math.PI) : 0;
    return { active: true, headToCup, beakOpen, reaction };
  }
}

/**
 * Pulse global `coffee_global_pulse` (§18.3) — café d'un AUTRE visiteur. Court et
 * léger (halo + petite réaction). PAS de défilé : un pic de cafés simultanés
 * RAFRAÎCHIT le même pulse au lieu d'en empiler dix.
 */
export class GlobalPulseController {
  private startedAt: number | null = null;
  private readonly durationMs = 700;

  trigger(now: number): void {
    this.startedAt = now;
  }

  /** Intensité 0..1 (montée/descente douce), 0 au repos. */
  sample(now: number): number {
    if (this.startedAt === null) {
      return 0;
    }
    const p = (now - this.startedAt) / this.durationMs;
    if (p >= 1) {
      this.startedAt = null;
      return 0;
    }
    return Math.sin(p * Math.PI);
  }
}

/**
 * AccessoryReveal (§10.5) — animation TRANSITOIRE de proclamation du gagnant.
 * Interrompt, se joue, revient à l'idle (comme coffee_receive). Priorité §17.6 au
 * niveau d'un coffee_receive. Ne porte PAS l'accessoire (montage = état persistant
 * géré par accessory_vote_controller) : juste le « ta-da ».
 */
export class RevealController {
  private startedAt: number | null = null;
  private readonly durationMs = 1100;

  trigger(now: number): void {
    this.startedAt = now;
  }

  /** Intensité 0..1 (montée/descente douce), 0 au repos. */
  sample(now: number): number {
    if (this.startedAt === null) {
      return 0;
    }
    const p = (now - this.startedAt) / this.durationMs;
    if (p >= 1) {
      this.startedAt = null;

      return 0;
    }

    return Math.sin(p * Math.PI);
  }
}

export type LoopHandle = { stop: () => void };

export function startRenderLoop(opts: {
  rig: DuckRig;
  theatre: DuckTheatre;
  coffee: CoffeeController;
  pulse: GlobalPulseController;
  reveal: RevealController;
  getEnergy: () => number;
  isReducedMotion: () => boolean;
}): LoopHandle {
  let raf = 0;

  const frame = (now: number): void => {
    const t = now / 1000;
    const base = resolveVisualTargets(opts.getEnergy());
    const idle = opts.theatre.idle.value;

    // ── Priorité §17.6 : reduced-motion > incident > mon café > pulse global > idle.
    if (opts.isReducedMotion()) {
      // 1. Réduction de mouvement : POSE STATIQUE lisible, aucun mouvement continu.
      opts.rig.apply(base, {
        breath: 0,
        blink: 0,
        wingPhase: 0,
        shakeX: 0,
        shakeY: 0,
        steamPulse: 0,
        coffee: NEUTRAL_COFFEE,
        halo: 0,
        reveal: 0,
        reducedMotion: true,
      });
    } else {
      const reveal = opts.reveal.sample(now); // révélation du gagnant (§10.5)
      const myCoffee = reveal > 0 ? NEUTRAL_COFFEE : opts.coffee.sample(now); // 3. MON café
      // 4. pulse global : seulement si ni révélation ni mon café (priorité §17.6).
      const pulse = reveal > 0 || myCoffee.active ? 0 : opts.pulse.sample(now);
      const coffee: CoffeeOverlay = myCoffee.active
        ? myCoffee
        : pulse > 0
          ? { active: true, headToCup: 0, beakOpen: 0, reaction: pulse } // halo + réaction, PAS de tasse
          : NEUTRAL_COFFEE;
      const incident = base.shakeIntensity > 0; // 2. incident : tremblement de #duck-root
      opts.rig.apply(base, {
        breath: Math.sin(t * idle.breathSpeed * Math.PI * 2),
        blink: blinkPulse(t, base.blinkSpeed * idle.blinkScale),
        wingPhase: coffee.active ? 0 : Math.sin(t * idle.wingSpeed),
        shakeX: incident ? noise(t * idle.shakeSpeed) : 0,
        shakeY: incident ? noise(t * idle.shakeSpeed + 99) : 0,
        steamPulse: (Math.sin(t * idle.steamSpeed * base.steamSpeed) + 1) / 2,
        coffee,
        halo: pulse,
        reveal,
        reducedMotion: false,
      });
    }
    raf = requestAnimationFrame(frame);
  };

  raf = requestAnimationFrame(frame);
  return { stop: () => cancelAnimationFrame(raf) };
}

function smooth(t: number): number {
  const c = Math.max(0, Math.min(1, t));
  return c * c * (3 - 2 * c);
}

/** Clignement bref en fin de chaque cycle (fréquence ≈ `freq` Hz). */
function blinkPulse(t: number, freq: number): number {
  const phase = (t * freq) % 1;
  return phase > 0.9 ? Math.sin(((phase - 0.9) / 0.1) * Math.PI) : 0;
}

/** Bruit pseudo-aléatoire borné −1..1 (tremblement). */
function noise(x: number): number {
  return (Math.sin(x) + Math.sin(x * 1.7 + 1) * 0.5) / 1.5;
}
