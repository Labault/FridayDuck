import { EnergySubscription, type EventSourceFactory } from './energySubscription.ts';

export type StreamMessageHandler = (raw: string) => void;

export type StreamSubscription = {
  /** Abonne un handler aux messages d'un `type` (§24.5). */
  on(type: string, handler: StreamMessageHandler): void;
  /** Désabonne ce contrôleur ; ferme l'EventSource si c'était le dernier. */
  close(): void;
};

/**
 * Flux temps réel de l'édition : UNE connexion EventSource par page, démultiplexée
 * par `type` (§24.5). Plusieurs contrôleurs (duck, accessory_vote) s'y abonnent ;
 * l'EventSource ne se ferme qu'au DERNIER désabonnement (ref-counting). Fermer à
 * la déconnexion d'un seul contrôleur couperait les autres ; ne jamais fermer
 * serait une fuite (§19.5).
 */
export class EditionStream {
  private readonly subscription: EnergySubscription;
  private readonly handlersByType = new Map<string, Set<StreamMessageHandler>>();
  private opened = false;
  private refCount = 0;

  constructor(factory: EventSourceFactory) {
    this.subscription = new EnergySubscription(factory);
  }

  connect(url: string): StreamSubscription {
    if (!this.opened && url !== '') {
      this.subscription.open(url, (raw) => this.dispatch(raw));
      this.opened = true;
    }
    this.refCount += 1;

    const owned = new Set<{ type: string; handler: StreamMessageHandler }>();
    let released = false;

    return {
      on: (type, handler) => {
        this.register(type, handler);
        owned.add({ type, handler });
      },
      close: () => {
        if (released) {
          return;
        }
        released = true;
        for (const entry of owned) {
          this.unregister(entry.type, entry.handler);
        }
        this.refCount -= 1;
        if (this.refCount === 0) {
          this.subscription.close();
          this.opened = false;
          this.handlersByType.clear();
        }
      },
    };
  }

  get isConnected(): boolean {
    return this.subscription.isOpen;
  }

  private dispatch(raw: string): void {
    const type = readType(raw);
    if (type === null) {
      return;
    }
    const handlers = this.handlersByType.get(type);
    if (handlers) {
      for (const handler of [...handlers]) {
        handler(raw);
      }
    }
  }

  private register(type: string, handler: StreamMessageHandler): void {
    let handlers = this.handlersByType.get(type);
    if (!handlers) {
      handlers = new Set();
      this.handlersByType.set(type, handlers);
    }
    handlers.add(handler);
  }

  private unregister(type: string, handler: StreamMessageHandler): void {
    this.handlersByType.get(type)?.delete(handler);
  }
}

function readType(raw: string): string | null {
  try {
    const data: unknown = JSON.parse(raw);
    if (typeof data === 'object' && data !== null && typeof (data as Record<string, unknown>).type === 'string') {
      return (data as Record<string, unknown>).type as string;
    }
  } catch {
    return null;
  }

  return null;
}

/** Singleton de page : la connexion partagée par tous les contrôleurs. */
export const editionStream = new EditionStream((url) => new EventSource(url, { withCredentials: true }));
