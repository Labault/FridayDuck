import { Controller } from '@hotwired/stimulus';
import { DuckRig } from '../duck/duckRig.ts';
import { energyState } from '../duck/energyState.ts';
import { createDuckTheatre, type DuckTheatre } from '../duck/theatre/project.ts';
import { initStudio } from '../duck/theatre/studio.ts';
import { CoffeeController, GlobalPulseController, RevealController, startRenderLoop, type LoopHandle } from '../duck/animation/loop.ts';
import { EnergyBarrier } from '../duck/state/energyBarrier.ts';
import { CoffeeActionKey, postCoffee } from '../duck/coffee/coffeeClient.ts';
import { reactToCoffee, type CoffeeReaction } from '../duck/coffee/coffeeReaction.ts';
import { ActionLedger } from '../duck/realtime/actionLedger.ts';
import { routeEnergyMessage } from '../duck/realtime/messageRouter.ts';
import { parseWinnerMessage } from '../duck/realtime/accessoryMessages.ts';
import { editionStream, type StreamSubscription } from '../duck/realtime/editionStream.ts';
import { AccessoryMounter } from '../duck/accessory/accessoryMounter.ts';

/**
 * Contrôleur principal du canard (§19.2), Phase 2b-ii.
 *
 * État initial via valeurs Twig (serveur, autorité). Toute mise à jour d'énergie
 * passe par la BARRIÈRE DE VERSION : le point d'entrée unique que la Phase 3
 * alimentera avec les messages Mercure. Offrir un café atteint le serveur, qui
 * recalcule l'énergie ; coffee_receive ne se joue que sur une acceptation réelle.
 */
export default class extends Controller<HTMLElement> {
  static values = {
    energy: Number,
    energyVersion: Number,
    remainingCoffees: Number,
    active: Boolean,
    status: String,
    coffeesUrl: String,
    mercureUrl: String,
    winner: Object,
  };

  static targets = ['scene', 'accessoryLabel', 'energyValue', 'energyState', 'remainingValue', 'coffeeButton', 'message', 'reducedFlag'];

  declare readonly energyValue: number;
  declare readonly energyVersionValue: number;
  declare readonly remainingCoffeesValue: number;
  declare readonly activeValue: boolean;
  declare readonly statusValue: string;
  declare readonly coffeesUrlValue: string;
  declare readonly mercureUrlValue: string;
  declare readonly hasMercureUrlValue: boolean;
  declare readonly winnerValue: { code?: string; label?: string; svgGroupId?: string };

  declare readonly hasSceneTarget: boolean;
  declare readonly sceneTarget: HTMLElement;
  declare readonly hasAccessoryLabelTarget: boolean;
  declare readonly accessoryLabelTarget: HTMLElement;
  declare readonly hasEnergyValueTarget: boolean;
  declare readonly energyValueTarget: HTMLElement;
  declare readonly hasEnergyStateTarget: boolean;
  declare readonly energyStateTarget: HTMLElement;
  declare readonly hasRemainingValueTarget: boolean;
  declare readonly remainingValueTarget: HTMLElement;
  declare readonly hasCoffeeButtonTarget: boolean;
  declare readonly coffeeButtonTarget: HTMLButtonElement;
  declare readonly hasMessageTarget: boolean;
  declare readonly messageTarget: HTMLElement;
  declare readonly hasReducedFlagTarget: boolean;
  declare readonly reducedFlagTarget: HTMLElement;

  private readonly barrier = new EnergyBarrier();
  private readonly actionKey = new CoffeeActionKey();
  private readonly coffee = new CoffeeController();
  private readonly pulse = new GlobalPulseController();
  private readonly reveal = new RevealController();
  private readonly ledger = new ActionLedger();
  private stream: StreamSubscription | null = null;
  private rig: DuckRig | null = null;
  private mounter: AccessoryMounter | null = null;
  private theatre: DuckTheatre | null = null;
  private loop: LoopHandle | null = null;
  private readonly reducedQuery = matchMedia('(prefers-reduced-motion: reduce)');
  private readonly onReducedChange = (): void => this.refreshReduced();
  private readonly onWinnerMount = (event: Event): void => this.handleWinnerMount(event);

  private activeNow = false;
  private remainingNow = 0;
  private inFlight = false;

  async connect(): Promise<void> {
    const scene = this.hasSceneTarget ? this.sceneTarget : this.element;
    this.rig = new DuckRig(scene);
    this.mounter = new AccessoryMounter(scene, this.hasAccessoryLabelTarget ? this.accessoryLabelTarget : null);
    // Gagnant déjà connu (arrivant après 14:00) : montage STATIQUE, sans proclamation.
    if (typeof this.winnerValue.svgGroupId === 'string' && this.winnerValue.svgGroupId !== '') {
      this.mounter.mount(this.winnerValue.svgGroupId, this.winnerValue.label ?? '');
    }

    // État initial (§19.3) + signal d'accessibilité (§28.1) rendus SYNCHRONEMENT,
    // AVANT toute initialisation Theatre.js. L'autorité serveur (pose, cafés
    // restants) et le mode reduced-motion ne doivent jamais attendre le
    // chargement de l'animation : sinon le canard reste « muet » tant que
    // Theatre.js n'est pas prêt (invisible en unit, visible en e2e navigateur).
    this.reducedQuery.addEventListener('change', this.onReducedChange);
    this.activeNow = this.activeValue;
    this.remainingNow = this.remainingCoffeesValue;
    this.barrier.apply({ energy: this.energyValue, energyVersion: this.energyVersionValue });
    this.refreshReadout();
    this.refreshReduced();
    this.updateButton();

    // Theatre.js + boucle de rendu : asynchrone, n'affecte pas l'état ci-dessus.
    await initStudio();
    this.theatre = createDuckTheatre();
    await this.theatre.project.ready;

    this.loop = startRenderLoop({
      rig: this.rig,
      theatre: this.theatre,
      coffee: this.coffee,
      pulse: this.pulse,
      reveal: this.reveal,
      // Dormant → énergie 0 (pose endormie) ; sinon l'énergie officielle.
      getEnergy: () => (this.activeNow ? this.barrier.energy : 0),
      isReducedMotion: () => this.reducedQuery.matches,
    });

    // UNE connexion Mercure partagée (§20), démultiplexée par type : l'énergie ici,
    // les résultats/gagnant côté accessory_vote_controller. Ref-comptée (§19.5).
    this.stream = editionStream.connect(this.hasMercureUrlValue ? this.mercureUrlValue : '');
    this.stream.on('ENERGY_CHANGED', (raw) => this.handleEnergyMessage(raw));
    // Révélation LIVE → montage persistant + flourish transitoire.
    this.stream.on('ACCESSORY_WINNER_SELECTED', (raw) => this.handleWinnerReveal(raw));
    // Repli (§20.6) : montage STATIQUE si Mercure est indisponible (depuis un POST tardif).
    window.addEventListener('duck:winner', this.onWinnerMount);
  }

  disconnect(): void {
    this.loop?.stop();
    this.loop = null;
    this.stream?.close(); // §19.5 : désabonnement (ferme l'EventSource si dernier)
    this.stream = null;
    window.removeEventListener('duck:winner', this.onWinnerMount);
    this.reducedQuery.removeEventListener('change', this.onReducedChange);
  }

  private handleWinnerMount(event: Event): void {
    const detail = (event as CustomEvent<{ svgGroupId?: string; label?: string }>).detail;
    if (typeof detail?.svgGroupId === 'string' && detail.svgGroupId !== '') {
      this.mounter?.mount(detail.svgGroupId, detail.label ?? ''); // montage statique, sans flourish
    }
  }

  private handleEnergyMessage(raw: string): void {
    const routing = routeEnergyMessage(raw, this.barrier, this.ledger);
    // coffee_global_pulse : café d'un AUTRE visiteur, jamais en reduced-motion (§17.6).
    if (routing.playPulse && !this.reducedQuery.matches) {
      this.pulse.trigger(performance.now());
    }
    // L'énergie a pu avancer ; les cafés restants ne changent JAMAIS (invariant B).
    if (routing.applied) {
      this.refreshReadout();
    }
  }

  private handleWinnerReveal(raw: string): void {
    const winner = parseWinnerMessage(raw);
    if (winner === null) {
      return;
    }
    // Montage PERSISTANT (toujours, même en reduced-motion).
    this.mounter?.mount(winner.svgGroupId, winner.label);
    // Flourish TRANSITOIRE de proclamation : seulement hors reduced-motion (§10.5).
    if (!this.reducedQuery.matches) {
      this.reveal.trigger(performance.now());
    }
  }

  async serveCoffee(): Promise<void> {
    if (this.inFlight || !this.canServe()) {
      return;
    }

    // Une clé par clic logique ; un retry réseau réutilisera la MÊME clé (§8.6).
    const actionId = this.actionKey.acquire(() => crypto.randomUUID());
    // Enregistre la clé comme MIENNE AVANT le POST : si l'écho Mercure précède la
    // réponse, son pulse global sera ignoré (dédup §18.3, invariant D).
    this.ledger.open(actionId);
    this.setInFlight(true);

    try {
      const outcome = await postCoffee(this.coffeesUrlValue, actionId);
      this.actionKey.settle(outcome);
      if (outcome.type !== 'NETWORK_ERROR') {
        this.ledger.settle(actionId); // TTL court après le résultat terminal
      }
      this.applyReaction(reactToCoffee(outcome, this.barrier));
    } finally {
      this.setInFlight(false);
    }
  }

  private applyReaction(reaction: CoffeeReaction): void {
    if (!reaction.active) {
      this.activeNow = false; // NOT_FRIDAY : autorité serveur → réconcilie DORMANT.
    }
    if (reaction.remainingCoffees !== null) {
      this.remainingNow = reaction.remainingCoffees;
    }
    // coffee_receive seulement sur acceptation réelle, jamais en reduced-motion.
    if (reaction.animate && !this.reducedQuery.matches) {
      this.coffee.trigger(performance.now(), this.theatre?.coffee.value.durationMs ?? 1800);
    }
    this.showMessage(reaction.message);
    this.refreshReadout();
    this.updateButton();
  }

  private canServe(): boolean {
    return this.activeNow && this.remainingNow > 0 && !this.inFlight;
  }

  private setInFlight(value: boolean): void {
    this.inFlight = value;
    this.updateButton();
  }

  private updateButton(): void {
    if (this.hasCoffeeButtonTarget) {
      this.coffeeButtonTarget.disabled = !this.canServe();
    }
  }

  private refreshReadout(): void {
    if (this.hasEnergyValueTarget) {
      this.energyValueTarget.textContent = String(this.activeNow ? this.barrier.energy : 0);
    }
    if (this.hasEnergyStateTarget) {
      this.energyStateTarget.textContent = this.activeNow
        ? energyState(this.barrier.energy).label
        : 'Dormant';
    }
    if (this.hasRemainingValueTarget) {
      this.remainingValueTarget.textContent = String(this.remainingNow);
    }
  }

  private refreshReduced(): void {
    if (this.hasReducedFlagTarget) {
      this.reducedFlagTarget.textContent = this.reducedQuery.matches
        ? 'reduced-motion : ON — poses statiques'
        : '';
    }
  }

  private showMessage(message: string | null): void {
    if (this.hasMessageTarget) {
      this.messageTarget.textContent = message ?? '';
    }
  }
}
