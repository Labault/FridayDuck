import { Controller } from '@hotwired/stimulus';
import { editionStream, type StreamSubscription } from '../duck/realtime/editionStream.ts';
import { SequenceBarrier } from '../duck/state/sequenceBarrier.ts';
import { parseReactionsMessage, putReaction, type ReactionCounts, type ReactionOutcome } from '../duck/advice/reactionClient.ts';

/**
 * Contrôleur du conseil + réactions (§30, Phase 5).
 *
 * LE CONSEIL VIT DANS LE DOM, PAS LE SVG (§18.5) → AUCUN Theatre.js. État initial
 * par valeurs Twig ; compteurs live via editionStream (ADVICE_REACTION_CHANGED →
 * barrière de séquence d'advice) ; réaction par PUT upsert (re-clic même réaction
 * = no-op local) ; partage par copie ; animations DOM/WAAPI ; reduced-motion → maj
 * sans animation.
 */
export default class extends Controller<HTMLElement> {
  static values = {
    text: String,
    reactions: Object,
    adviceReaction: String,
    adviceSequence: Number,
    reactionUrl: String,
    mercureUrl: String,
  };

  static targets = ['button', 'message', 'card', 'stamp', 'copyFeedback'];

  declare readonly textValue: string;
  declare readonly reactionsValue: ReactionCounts;
  declare readonly adviceReactionValue: string;
  declare readonly adviceSequenceValue: number;
  declare readonly reactionUrlValue: string;
  declare readonly mercureUrlValue: string;
  declare readonly hasMercureUrlValue: boolean;

  declare readonly buttonTargets: HTMLButtonElement[];
  declare readonly hasMessageTarget: boolean;
  declare readonly messageTarget: HTMLElement;
  declare readonly hasCardTarget: boolean;
  declare readonly cardTarget: HTMLElement;
  declare readonly hasStampTarget: boolean;
  declare readonly stampTarget: HTMLElement;
  declare readonly hasCopyFeedbackTarget: boolean;
  declare readonly copyFeedbackTarget: HTMLElement;

  private readonly barrier = new SequenceBarrier();
  private readonly reducedQuery = matchMedia('(prefers-reduced-motion: reduce)');
  private stream: StreamSubscription | null = null;
  private counts: ReactionCounts = { CONCERNING: 0, ALREADY_DONE: 0, TAKING_NOTES: 0 };
  private myReaction: string | null = null;
  private inFlight = false;

  connect(): void {
    this.barrier.apply(this.adviceSequenceValue);
    this.counts = { ...this.reactionsValue };
    this.myReaction = '' !== this.adviceReactionValue ? this.adviceReactionValue : null;

    this.renderCounts(false);
    this.renderHighlight();

    this.stream = editionStream.connect(this.hasMercureUrlValue ? this.mercureUrlValue : '');
    this.stream.on('ADVICE_REACTION_CHANGED', (raw) => this.handleReactions(raw));

    this.appear();
  }

  disconnect(): void {
    this.stream?.close(); // §19.5 : désabonnement ref-compté
    this.stream = null;
  }

  react(event: Event): void {
    const button = event.currentTarget;
    if (!(button instanceof HTMLElement)) {
      return;
    }
    const reaction = button.dataset.reaction;
    // Re-clic sur sa propre réaction → no-op local (le serveur fait autorité).
    if (typeof reaction !== 'string' || reaction === this.myReaction || this.inFlight) {
      return;
    }
    void this.submit(reaction);
  }

  private async submit(reaction: string): Promise<void> {
    this.setInFlight(true);
    try {
      this.applyOutcome(await putReaction(this.reactionUrlValue, reaction));
    } finally {
      this.setInFlight(false);
    }
  }

  private applyOutcome(outcome: ReactionOutcome): void {
    switch (outcome.type) {
      case 'RECORDED':
        this.myReaction = outcome.reaction;
        this.renderHighlight();
        if (outcome.changed && this.barrier.apply(outcome.adviceSequence)) {
          this.counts = outcome.reactions;
          this.renderCounts(true);
        }
        this.showMessage('Réaction enregistrée.');
        break;
      case 'NOT_FRIDAY':
        this.showMessage('Le canard ne donne son conseil que le vendredi.');
        break;
      case 'INVALID_REACTION':
        this.showMessage('Réaction invalide.');
        break;
      case 'NETWORK_ERROR':
        this.showMessage('Réseau indisponible — réessayez.');
        break;
    }
  }

  private handleReactions(raw: string): void {
    const message = parseReactionsMessage(raw);
    // Barrière de séquence d'advice (§20.4) : applique seulement si elle avance.
    if (message === null || !this.barrier.apply(message.adviceSequence)) {
      return;
    }
    this.counts = message.reactions;
    this.renderCounts(true);
  }

  async copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(this.textValue);
      this.copyFeedback('Conseil copié ✓');
    } catch {
      this.copyFeedback('Copie indisponible');
    }
  }

  private renderCounts(animate: boolean): void {
    for (const button of this.buttonTargets) {
      const reaction = button.dataset.reaction ?? '';
      const count = this.countFor(reaction);
      const node = button.querySelector('[data-role="count"]');
      if (node) {
        const previous = node.textContent;
        node.textContent = String(count);
        if (animate && previous !== String(count) && !this.reducedQuery.matches) {
          node.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.35)' }, { transform: 'scale(1)' }], { duration: 280, easing: 'ease-out' });
        }
      }
    }
  }

  private renderHighlight(): void {
    for (const button of this.buttonTargets) {
      button.classList.toggle('is-mine', this.myReaction === (button.dataset.reaction ?? ''));
      button.setAttribute('aria-pressed', this.myReaction === (button.dataset.reaction ?? '') ? 'true' : 'false');
    }
  }

  private appear(): void {
    if (this.reducedQuery.matches) {
      return;
    }
    if (this.hasCardTarget) {
      this.cardTarget.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }], { duration: 400, easing: 'ease-out' });
    }
    if (this.hasStampTarget) {
      // Tampon « conseil officiel » (§18.5).
      this.stampTarget.animate(
        [{ opacity: 0, transform: 'rotate(-12deg) scale(1.6)' }, { opacity: 1, transform: 'rotate(-12deg) scale(1)' }],
        { duration: 450, delay: 250, easing: 'ease-out', fill: 'backwards' },
      );
    }
  }

  private copyFeedback(text: string): void {
    if (!this.hasCopyFeedbackTarget) {
      return;
    }
    this.copyFeedbackTarget.textContent = text;
    if (!this.reducedQuery.matches) {
      this.copyFeedbackTarget.animate([{ opacity: 0 }, { opacity: 1 }, { opacity: 1 }, { opacity: 0 }], { duration: 1600, easing: 'ease' });
    }
  }

  private countFor(reaction: string): number {
    if (reaction === 'CONCERNING' || reaction === 'ALREADY_DONE' || reaction === 'TAKING_NOTES') {
      return this.counts[reaction];
    }

    return 0;
  }

  private setInFlight(value: boolean): void {
    this.inFlight = value;
    for (const button of this.buttonTargets) {
      button.disabled = value;
    }
  }

  private showMessage(text: string): void {
    if (this.hasMessageTarget) {
      this.messageTarget.textContent = text;
    }
  }
}
