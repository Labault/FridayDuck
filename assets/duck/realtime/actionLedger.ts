/**
 * Registre des client-action-ids émis par MES propres POST café (dédup §18.3).
 *
 * Sert à absorber l'écho Mercure de mon propre café quel que soit l'ordre
 * d'arrivée (Mercure peut PRÉCÉDER la réponse POST) : j'enregistre la clé AVANT
 * d'envoyer le POST, et je la garde encore quelques secondes APRÈS le résultat
 * terminal. Tant qu'une clé est mienne, son pulse global est ignoré (coffee_receive
 * est déjà joué par le chemin POST — invariant D : jamais les deux).
 */
export class ActionLedger {
  /** actionId → expiration (ms) ; null = en cours (POST non terminé). */
  private readonly entries = new Map<string, number | null>();

  constructor(
    private readonly ttlMs = 5000,
    private readonly now: () => number = () => performance.now(),
  ) {}

  /** À l'émission d'un POST : la clé est mienne, sans expiration tant qu'en vol. */
  open(actionId: string): void {
    this.entries.set(actionId, null);
  }

  /** Au résultat terminal du POST : la clé expire après un court TTL. */
  settle(actionId: string): void {
    this.entries.set(actionId, this.now() + this.ttlMs);
  }

  /** La clé est-elle (encore) mienne ? Élague les expirées au passage. */
  isMine(actionId: string): boolean {
    this.prune();
    return this.entries.has(actionId);
  }

  private prune(): void {
    const t = this.now();
    for (const [id, expiry] of this.entries) {
      if (expiry !== null && expiry <= t) {
        this.entries.delete(id);
      }
    }
  }
}
