import { Controller } from '@hotwired/stimulus';
import { editionStream, type StreamSubscription } from '../duck/realtime/editionStream.ts';
import {
  parseResultsMessage,
  parseWinnerMessage,
  readWinner,
  type WinnerMessage,
} from '../duck/realtime/accessoryMessages.ts';
import { SequenceBarrier } from '../duck/state/sequenceBarrier.ts';
import { postVote, type VoteOutcome } from '../duck/vote/voteClient.ts';
import { canVote, leaderCode, percentage, totalVotes, voteMode, type OptionTally, type VoteMode } from '../duck/vote/voteUiState.ts';

type OptionValue = { code: string; label: string; displayOrder: number; voteCount: number };

/**
 * Contrôleur du vote d'accessoire (§30, Phase 4b).
 *
 * État initial par VALEURS TWIG ; résultats live via la barrière de séquence
 * (§20.4) ; vote par POST (sans clé d'idempotence — UNIQUE serveur) ; décompte
 * cosmétique (§10.3, le serveur fait foi) ; proclamation du gagnant (§10.6). Le
 * MONTAGE de l'accessoire est délégué au duck_controller (qui possède le SVG) via
 * son abonnement Mercure, avec repli `duck:winner` si Mercure est indisponible.
 */
export default class extends Controller<HTMLElement> {
  static values = {
    options: Array,
    hasVoted: Boolean,
    votedAccessory: String,
    open: Boolean,
    closesAt: String,
    winner: Object,
    resultsSequence: Number,
    votesUrl: String,
    mercureUrl: String,
  };

  static targets = ['option', 'message', 'countdown', 'proclamation'];

  declare readonly optionsValue: OptionValue[];
  declare readonly hasVotedValue: boolean;
  declare readonly votedAccessoryValue: string;
  declare readonly openValue: boolean;
  declare readonly closesAtValue: string;
  declare readonly winnerValue: Record<string, unknown>;
  declare readonly resultsSequenceValue: number;
  declare readonly votesUrlValue: string;
  declare readonly mercureUrlValue: string;
  declare readonly hasMercureUrlValue: boolean;

  declare readonly optionTargets: HTMLElement[];
  declare readonly hasMessageTarget: boolean;
  declare readonly messageTarget: HTMLElement;
  declare readonly hasCountdownTarget: boolean;
  declare readonly countdownTarget: HTMLElement;
  declare readonly hasProclamationTarget: boolean;
  declare readonly proclamationTarget: HTMLElement;

  private readonly resultsBarrier = new SequenceBarrier();
  private stream: StreamSubscription | null = null;
  private tally: OptionTally[] = [];
  private mode: VoteMode = 'open-can-vote';
  private myChoice: string | null = null;
  private inFlight = false;
  private countdownExpired = false;
  private countdownTimer: number | null = null;

  connect(): void {
    this.resultsBarrier.apply(this.resultsSequenceValue);
    this.tally = this.optionsValue.map((option) => ({ code: option.code, voteCount: option.voteCount, displayOrder: option.displayOrder }));
    this.myChoice = '' !== this.votedAccessoryValue ? this.votedAccessoryValue : null;
    this.mode = voteMode(this.openValue, this.hasVotedValue);

    this.renderResults();
    this.renderMode();

    // Gagnant déjà connu (arrivant tardif) → proclamation STATIQUE (sans fanfare).
    const initialWinner = readWinner(this.winnerValue);
    if (initialWinner !== null) {
      this.proclaim(initialWinner, false);
    }

    this.stream = editionStream.connect(this.hasMercureUrlValue ? this.mercureUrlValue : '');
    this.stream.on('ACCESSORY_RESULTS_UPDATED', (raw) => this.handleResults(raw));
    this.stream.on('ACCESSORY_WINNER_SELECTED', (raw) => this.handleWinner(raw));

    this.startCountdown();
  }

  disconnect(): void {
    this.stream?.close(); // §19.5 : désabonnement ref-compté
    this.stream = null;
    this.stopCountdown();
  }

  vote(event: Event): void {
    const button = event.currentTarget;
    if (!(button instanceof HTMLElement)) {
      return;
    }
    const code = button.dataset.code;
    if (typeof code !== 'string' || !canVote(this.mode, this.countdownExpired, this.inFlight)) {
      return;
    }
    void this.submit(code);
  }

  private async submit(code: string): Promise<void> {
    this.setInFlight(true);
    try {
      this.applyOutcome(await postVote(this.votesUrlValue, code), code);
    } finally {
      this.setInFlight(false);
    }
  }

  private applyOutcome(outcome: VoteOutcome, attempted: string): void {
    switch (outcome.type) {
      case 'ACCEPTED':
        this.lockOnChoice(outcome.accessory, 'Vote enregistré.');
        break;
      case 'ALREADY_VOTED':
        this.lockOnChoice(this.myChoice ?? attempted, 'Vous avez déjà voté.');
        break;
      case 'VOTE_CLOSED':
        this.close('Le vote est clôturé.');
        if (outcome.winner !== null) {
          this.proclaim(outcome.winner, false);
          this.requestMount(outcome.winner); // repli si Mercure indisponible (§20.6)
        }
        break;
      case 'INVALID_ACCESSORY':
        this.showMessage('Accessoire invalide.');
        break;
      case 'NOT_FRIDAY':
        this.close('Le canard ne vote que le vendredi.');
        break;
      case 'NETWORK_ERROR':
        this.showMessage('Réseau indisponible — réessayez.');
        break;
    }
  }

  private handleResults(raw: string): void {
    const message = parseResultsMessage(raw);
    // Barrière de séquence (§20.4) : applique seulement si la séquence avance.
    if (message === null || !this.resultsBarrier.apply(message.resultsSequence)) {
      return;
    }
    this.tally = message.options.map((option) => ({ code: option.code, voteCount: option.voteCount, displayOrder: option.displayOrder }));
    this.renderResults();
  }

  private handleWinner(raw: string): void {
    const winner = parseWinnerMessage(raw);
    if (winner === null) {
      return;
    }
    this.close('');
    this.proclaim(winner, true); // live → proclamation (le duck_controller monte + flourish)
  }

  private lockOnChoice(choice: string, message: string): void {
    this.myChoice = choice;
    this.mode = 'open-voted';
    this.renderMode();
    this.showMessage(message);
  }

  private close(message: string): void {
    this.mode = 'closed';
    this.renderMode();
    if ('' !== message) {
      this.showMessage(message);
    }
  }

  private renderResults(): void {
    const total = totalVotes(this.tally);
    const leader = leaderCode(this.tally);
    for (const element of this.optionTargets) {
      const code = element.dataset.code ?? '';
      const option = this.tally.find((entry) => entry.code === code);
      const count = option?.voteCount ?? 0;
      this.text(element, '[data-role="count"]', String(count));
      this.text(element, '[data-role="pct"]', `${percentage(count, total)} %`);
      element.classList.toggle('is-leader', leader === code);
    }
  }

  private renderMode(): void {
    const votable = canVote(this.mode, this.countdownExpired, this.inFlight);
    for (const element of this.optionTargets) {
      if (element instanceof HTMLButtonElement) {
        element.disabled = !votable;
      }
      element.classList.toggle('is-mine', this.myChoice === (element.dataset.code ?? ''));
    }
  }

  private proclaim(winner: WinnerMessage, live: boolean): void {
    if (this.hasProclamationTarget) {
      this.proclamationTarget.textContent = `Accessoire gagnant : ${winner.label}.`;
      this.proclamationTarget.hidden = false;
      this.proclamationTarget.classList.toggle('is-live', live);
    }
  }

  private requestMount(winner: WinnerMessage): void {
    window.dispatchEvent(new CustomEvent('duck:winner', { detail: { svgGroupId: winner.svgGroupId, label: winner.label } }));
  }

  private startCountdown(): void {
    if (!this.hasCountdownTarget || 'closed' === this.mode) {
      return;
    }
    this.tickCountdown();
    this.countdownTimer = window.setInterval(() => this.tickCountdown(), 1000);
  }

  private tickCountdown(): void {
    const closesAt = Date.parse(this.closesAtValue);
    const remaining = Number.isNaN(closesAt) ? 0 : closesAt - Date.now();
    if (remaining <= 0) {
      // Décompte COSMÉTIQUE expiré : on désactive localement (optimiste), mais
      // l'autorité de clôture reste le serveur (ACCESSORY_WINNER_SELECTED / VOTE_CLOSED).
      this.countdownExpired = true;
      this.countdownTarget.textContent = 'Vote en cours de clôture…';
      this.renderMode();
      this.stopCountdown();

      return;
    }
    const totalSeconds = Math.floor(remaining / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    this.countdownTarget.textContent = `Clôture dans ${minutes} min ${String(seconds).padStart(2, '0')} s`;
  }

  private stopCountdown(): void {
    if (this.countdownTimer !== null) {
      window.clearInterval(this.countdownTimer);
      this.countdownTimer = null;
    }
  }

  private setInFlight(value: boolean): void {
    this.inFlight = value;
    this.renderMode();
  }

  private text(root: HTMLElement, selector: string, value: string): void {
    const node = root.querySelector(selector);
    if (node) {
      node.textContent = value;
    }
  }

  private showMessage(text: string): void {
    if (this.hasMessageTarget) {
      this.messageTarget.textContent = text;
    }
  }
}
