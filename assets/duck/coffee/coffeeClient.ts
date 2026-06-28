/**
 * Client POST /api/friday/current/coffees (§24.2) et gestion de la clé
 * d'idempotence (§8.6).
 */

export type CoffeeApiOutcome =
  | { type: 'ACCEPTED'; energy: number; energyVersion: number; remainingCoffees: number; contributionId: string }
  | { type: 'REPLAYED'; energy: number; energyVersion: number; remainingCoffees: number }
  | { type: 'NOT_FRIDAY' }
  | { type: 'LIMIT_REACHED' }
  | { type: 'INVALID_KEY' }
  | { type: 'NETWORK_ERROR' };

type CoffeeSuccessBody = {
  replayed: boolean;
  currentEnergy: number;
  energyVersion: number;
  remainingCoffeesForVisitor: number;
  coffeeContributionId: string;
};

export type FetchLike = typeof fetch;

/**
 * Envoie un café. La clé d'action est transmise en `Idempotency-Key` (le serveur
 * compose la clé complète, §8.6). Normalise chaque réponse en {@link CoffeeApiOutcome} :
 * 200 → ACCEPTED|REPLAYED selon `replayed` ; 409/429/400 → erreurs métier ;
 * échec réseau → NETWORK_ERROR (retriable, même clé).
 */
export async function postCoffee(
  url: string,
  actionId: string,
  fetchImpl: FetchLike = fetch,
): Promise<CoffeeApiOutcome> {
  let response: Response;
  try {
    response = await fetchImpl(url, {
      method: 'POST',
      headers: { 'Idempotency-Key': actionId, Accept: 'application/json' },
    });
  } catch {
    return { type: 'NETWORK_ERROR' };
  }

  if (response.status === 200) {
    const body = (await response.json()) as CoffeeSuccessBody;
    const base = {
      energy: body.currentEnergy,
      energyVersion: body.energyVersion,
      remainingCoffees: body.remainingCoffeesForVisitor,
    };
    return body.replayed
      ? { type: 'REPLAYED', ...base }
      : { type: 'ACCEPTED', ...base, contributionId: body.coffeeContributionId };
  }

  switch (response.status) {
    case 409:
      return { type: 'NOT_FRIDAY' };
    case 429:
      return { type: 'LIMIT_REACHED' };
    case 400:
      return { type: 'INVALID_KEY' };
    default:
      return { type: 'NETWORK_ERROR' };
  }
}

/**
 * Clé d'idempotence par CLIC LOGIQUE. Conservée tant que l'issue n'est pas
 * terminale : un retry réseau réutilise la MÊME clé (le serveur dédoublonne).
 * Libérée dès qu'une réponse HTTP arrive (terminal) → le prochain clic réel a une
 * nouvelle clé.
 */
export class CoffeeActionKey {
  private pending: string | null = null;

  acquire(generate: () => string): string {
    if (this.pending === null) {
      this.pending = generate();
    }
    return this.pending;
  }

  settle(outcome: CoffeeApiOutcome): void {
    if (outcome.type !== 'NETWORK_ERROR') {
      this.pending = null;
    }
  }

  get hasPending(): boolean {
    return this.pending !== null;
  }
}
