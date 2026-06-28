export type EnergySnapshot = {
  energy: number;
  energyVersion: number;
};

/**
 * Barrière de version — la couture clé (§19, Phase 2b-ii).
 *
 * Point d'entrée UNIQUE de toute mise à jour d'énergie (état initial, réponse
 * POST, et plus tard messages Mercure en Phase 3). N'accepte un état que si sa
 * version est STRICTEMENT supérieure à la version courante ; sinon il est ignoré
 * (état périmé / arrivé dans le désordre). Le serveur reste l'autorité.
 */
export class EnergyBarrier {
  private currentEnergy = 0;
  private currentVersion = Number.NEGATIVE_INFINITY;

  /** @returns true si l'état a été appliqué (version plus récente), false sinon. */
  apply(snapshot: EnergySnapshot): boolean {
    if (snapshot.energyVersion <= this.currentVersion) {
      return false;
    }
    this.currentVersion = snapshot.energyVersion;
    this.currentEnergy = snapshot.energy;
    return true;
  }

  get energy(): number {
    return this.currentEnergy;
  }

  get appliedVersion(): number {
    return this.currentVersion === Number.NEGATIVE_INFINITY ? -1 : this.currentVersion;
  }
}
