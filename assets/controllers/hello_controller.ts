import { Controller } from '@hotwired/stimulus';

/**
 * Contrôleur trivial : prouve que le pipeline Stimulus + Vite + TypeScript se
 * charge dans Symfony (tâche 1 de la Phase 2b-i).
 */
export default class extends Controller<HTMLElement> {
  static targets = ['output'];

  declare readonly hasOutputTarget: boolean;
  declare readonly outputTarget: HTMLElement;

  connect(): void {
    this.element.dataset.helloConnected = 'true';
    if (this.hasOutputTarget) {
      this.outputTarget.textContent = 'Stimulus + Vite + TypeScript : chargé ✓';
    }
  }
}
