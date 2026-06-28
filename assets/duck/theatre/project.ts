import { getProject, types, type IProject, type ISheetObject } from '@theatre/core';

/**
 * Projet/feuilles Theatre.js (§17.2). Les feuilles portent les PARAMÈTRES de
 * l'idle et du café (éditables dans le Studio, exportés dans l'état versionné
 * §17.3). La boucle lit ces paramètres et les combine avec les cibles d'énergie.
 *
 * Theatre.js ne décide de rien (§15.5) : il ne fait que paramétrer/animer.
 */

// Charge l'état exporté s'il a été déposé (sinon valeurs par défaut). Le glob
// optionnel évite toute erreur de build quand le fichier est absent.
const stateModules = import.meta.glob('./duck-friday.state.json', {
  eager: true,
  import: 'default',
});
const savedState = Object.values(stateModules)[0] as object | undefined;

const idleProps = {
  breathSpeed: types.number(0.55, { range: [0.1, 3], label: 'Respiration (Hz)' }),
  wingSpeed: types.number(7, { range: [1, 18], label: 'Ailes (Hz)' }),
  blinkScale: types.number(1, { range: [0.2, 3], label: 'Clignement ×' }),
  steamSpeed: types.number(1.2, { range: [0.2, 5], label: 'Vapeur (×)' }),
  shakeSpeed: types.number(38, { range: [5, 90], label: 'Tremblement (Hz)' }),
};

const coffeeProps = {
  durationMs: types.number(1800, { range: [600, 3000], label: 'Durée café (ms)' }),
};

export type DuckTheatre = {
  project: IProject;
  idle: ISheetObject<typeof idleProps>;
  coffee: ISheetObject<typeof coffeeProps>;
};

export function createDuckTheatre(): DuckTheatre {
  const project = getProject('DuckFriday', savedState ? { state: savedState } : undefined);

  const idle = project.sheet('DuckIdle').object('Idle', idleProps);
  const coffee = project.sheet('CoffeeAction').object('Coffee', coffeeProps);

  // Feuilles du §17.2 présentes pour l'édition future (accessoires, incident).
  project.sheet('AccessoryReveal').object('Accessory', {
    popScale: types.number(1, { range: [0.5, 2], label: 'Apparition ×' }),
  });
  project.sheet('CaffeineIncident').object('Incident', {
    extraShake: types.number(1, { range: [0, 3], label: 'Tremblement incident ×' }),
  });

  return { project, idle, coffee };
}
