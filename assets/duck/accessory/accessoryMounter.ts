/**
 * Montage de l'accessoire gagnant (§10.5) — état PERSISTANT, distinct de
 * l'animation de révélation.
 *
 * Révèle le groupe placeholder keyé par `svgGroupId` (déjà présent dans le bon
 * slot SVG, donc il RIDE la tête/le corps par héritage) et expose l'alternative
 * textuelle (§28.3). Indépendant du cycle d'animation : reste monté jusqu'à
 * minuit (tant que le contrôleur vit).
 */
export class AccessoryMounter {
  constructor(
    private readonly scene: ParentNode,
    private readonly label: HTMLElement | null,
  ) {}

  mount(svgGroupId: string, label: string): void {
    for (const mounted of this.scene.querySelectorAll('.duck-accessory.is-mounted')) {
      mounted.classList.remove('is-mounted');
    }
    this.scene.querySelector(`[id="${svgGroupId}"]`)?.classList.add('is-mounted');

    if (this.label) {
      this.label.textContent = `Accessoire porté : ${label}.`;
    }
  }

  /** Id du groupe actuellement monté (pour les tests / l'idempotence). */
  get mountedGroupId(): string | null {
    return this.scene.querySelector('.duck-accessory.is-mounted')?.id ?? null;
  }
}
