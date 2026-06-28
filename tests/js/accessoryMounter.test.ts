// @vitest-environment happy-dom
import { beforeEach, describe, expect, it } from 'vitest';
import sceneSvg from '../../templates/duck/_scene.svg.twig?raw';
import { AccessoryMounter } from '../../assets/duck/accessory/accessoryMounter.ts';

describe('AccessoryMounter — persistent mount + text alternative (§10.5, §28.3)', () => {
  let scene: HTMLElement;
  let label: HTMLElement;

  beforeEach(() => {
    scene = document.createElement('div');
    scene.innerHTML = sceneSvg;
    label = document.createElement('p');
  });

  it('mounts the winner group and exposes the text alternative', () => {
    const mounter = new AccessoryMounter(scene, label);

    mounter.mount('accessory-cto-glasses', 'Lunettes de soleil de CTO');

    expect(scene.querySelector('#accessory-cto-glasses')?.classList.contains('is-mounted')).toBe(true);
    expect(mounter.mountedGroupId).toBe('accessory-cto-glasses');
    expect(label.textContent).toBe('Accessoire porté : Lunettes de soleil de CTO.');
  });

  it('unmounts the previous accessory when mounting another (one at a time)', () => {
    const mounter = new AccessoryMounter(scene, label);

    mounter.mount('accessory-cto-glasses', 'A');
    mounter.mount('accessory-kubernetes-beanie', 'B');

    expect(scene.querySelector('#accessory-cto-glasses')?.classList.contains('is-mounted')).toBe(false);
    expect(scene.querySelector('#accessory-kubernetes-beanie')?.classList.contains('is-mounted')).toBe(true);
  });

  it('head accessories live INSIDE the head group, so they ride the head (§10.5)', () => {
    const head = scene.querySelector('#duck-head');

    // Le placeholder est un descendant de #duck-head → hérite des transformations de la tête.
    expect(head?.querySelector('#accessory-cto-glasses')).not.toBeNull();
  });
});
