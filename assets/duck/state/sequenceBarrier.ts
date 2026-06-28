/**
 * Barrière de séquence générique (§20.4) : n'accepte une mise à jour que si sa
 * séquence est STRICTEMENT supérieure à la courante. Même principe que la
 * barrière d'énergie, appliqué aux résultats de vote (`resultsSequence`).
 */
export class SequenceBarrier {
  private current = Number.NEGATIVE_INFINITY;

  /** @returns true si la séquence avance (à appliquer), false si périmée. */
  apply(sequence: number): boolean {
    if (sequence <= this.current) {
      return false;
    }
    this.current = sequence;

    return true;
  }

  get value(): number {
    return this.current === Number.NEGATIVE_INFINITY ? -1 : this.current;
  }
}
