/**
 * Abonnement temps réel à l'état d'énergie (§19.5, §20).
 *
 * Encapsule l'EventSource derrière une fabrique injectable pour rester testable :
 * ouverture idempotente, fermeture propre à `disconnect()`. La reconnexion sur
 * coupure reste gérée NATIVEMENT par EventSource (retry) — on ne la réinvente pas.
 */
export interface EventSourceLike {
  addEventListener(type: 'message', listener: (event: MessageEvent) => void): void;
  close(): void;
}

export type EventSourceFactory = (url: string) => EventSourceLike;

export class EnergySubscription {
  private source: EventSourceLike | null = null;

  constructor(private readonly factory: EventSourceFactory) {}

  open(url: string, onMessage: (raw: string) => void): void {
    if (url === '' || this.source !== null) {
      return;
    }
    this.source = this.factory(url);
    this.source.addEventListener('message', (event) => onMessage(String(event.data)));
  }

  close(): void {
    this.source?.close();
    this.source = null;
  }

  get isOpen(): boolean {
    return this.source !== null;
  }
}
