/** États d'énergie du MVP (§9.1). Sert d'étiquette lisible dans la démo. */

export type EnergyStateCode =
  | 'COMATOSE'
  | 'PHYSICALLY_PRESENT'
  | 'BARELY_FUNCTIONAL'
  | 'PERFORMING_PRODUCTIVITY'
  | 'OVERMOTIVATED'
  | 'CAFFEINE_INCIDENT';

export type EnergyState = {
  code: EnergyStateCode;
  label: string;
};

const STATES: ReadonlyArray<{ max: number; state: EnergyState }> = [
  { max: 10, state: { code: 'COMATOSE', label: 'Comateux' } },
  { max: 30, state: { code: 'PHYSICALLY_PRESENT', label: 'Présent physiquement' } },
  { max: 50, state: { code: 'BARELY_FUNCTIONAL', label: 'Presque fonctionnel' } },
  { max: 70, state: { code: 'PERFORMING_PRODUCTIVITY', label: 'Productif en apparence' } },
  { max: 90, state: { code: 'OVERMOTIVATED', label: 'Beaucoup trop motivé' } },
  { max: 100, state: { code: 'CAFFEINE_INCIDENT', label: 'Incident caféiné' } },
];

export function energyState(energy: number): EnergyState {
  const clamped = Math.max(0, Math.min(100, energy));
  for (const { max, state } of STATES) {
    if (clamped <= max) {
      return state;
    }
  }
  // Inatteignable (clamped ≤ 100), mais TypeScript veut un retour total.
  return STATES[STATES.length - 1]!.state;
}
