// @vitest-environment happy-dom
import { beforeEach, describe, expect, it } from 'vitest';
// Le partial Twig est la SOURCE UNIQUE du SVG (rendu inline par Symfony) ; le
// test en importe le contenu brut pour vérifier le mapping rig → groupes.
import duckSvg from '../../templates/duck/_scene.svg.twig?raw';
import { DuckRig, NEUTRAL_COFFEE, type DuckModulation } from '../../assets/duck/duckRig.ts';
import { resolveVisualTargets } from '../../assets/duck/visualProperties.ts';

const STILL: DuckModulation = {
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
};

function style(mount: HTMLElement, id: string): CSSStyleDeclaration {
  const node = mount.querySelector<SVGGraphicsElement>(`#${id}`);
  if (!node) {
    throw new Error(`#${id} introuvable`);
  }
  return node.style;
}

describe('DuckRig maps targets to the right SVG groups (energy → pose)', () => {
  let mount: HTMLElement;
  let rig: DuckRig;

  beforeEach(() => {
    mount = document.createElement('div');
    mount.innerHTML = duckSvg;
    document.body.appendChild(mount);
    rig = new DuckRig(mount);
  });

  it('at energy 0: eyelids closed, all secondary objects hidden', () => {
    rig.apply(resolveVisualTargets(0), STILL);

    expect(style(mount, 'duck-left-eyelid').transform).toBe('scaleY(1)');
    expect(style(mount, 'duck-right-eyelid').transform).toBe('scaleY(1)');
    expect(style(mount, 'keyboard').opacity).toBe('0');
    expect(style(mount, 'terminal').opacity).toBe('0');
    expect(style(mount, 'particles').opacity).toBe('0');
    // Corps abaissé (bodyY = 10) → translation Y positive.
    expect(style(mount, 'duck-root').transform).toContain('translate(0px, 55px)');
  });

  it('at energy 100: eyelids open, keyboard/terminal/particles fully shown', () => {
    rig.apply(resolveVisualTargets(100), STILL);

    expect(style(mount, 'duck-left-eyelid').transform).toBe('scaleY(0)');
    expect(style(mount, 'duck-right-eyelid').transform).toBe('scaleY(0)');
    expect(style(mount, 'keyboard').opacity).toBe('1');
    expect(style(mount, 'terminal').opacity).toBe('1');
    expect(style(mount, 'particles').opacity).toBe('1');
  });

  it('reveals the keyboard at 51 but not the terminal (51 ≤ energy < 71)', () => {
    rig.apply(resolveVisualTargets(51), STILL);

    expect(style(mount, 'keyboard').opacity).toBe('1');
    expect(style(mount, 'terminal').opacity).toBe('0');
  });

  it('opens the beak only while a coffee is being drunk', () => {
    rig.apply(resolveVisualTargets(60), { ...STILL, reducedMotion: false });
    expect(style(mount, 'duck-beak').transform).toBe('scaleY(1)');

    rig.apply(resolveVisualTargets(60), {
      ...STILL,
      reducedMotion: false,
      coffee: { active: true, headToCup: 1, beakOpen: 1, reaction: 0 },
    });
    expect(style(mount, 'duck-beak').transform).toBe('scaleY(1.5)');
    // La tête se penche vers la tasse (rotation modifiée par le café).
    expect(style(mount, 'duck-head').transform).not.toBe('rotate(0deg)');
  });
});
