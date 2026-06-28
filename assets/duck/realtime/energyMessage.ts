/**
 * Message d'énergie reçu via Mercure (§20.3, charge minimale §18.3 / invariant B).
 * État GLOBAL uniquement — jamais de cafés restants par-visiteur.
 */
export type EnergyMessage = {
  energy: number;
  energyVersion: number;
  actionId: string;
};

/**
 * Parse un message Mercure brut. Retourne null si malformé (un message invalide
 * ne doit jamais casser le flux temps réel ni toucher l'état).
 */
export function parseEnergyMessage(raw: string): EnergyMessage | null {
  let data: unknown;
  try {
    data = JSON.parse(raw);
  } catch {
    return null;
  }

  if (typeof data !== 'object' || data === null) {
    return null;
  }

  const candidate = data as Record<string, unknown>;
  if (
    typeof candidate.energy !== 'number' ||
    typeof candidate.energyVersion !== 'number' ||
    typeof candidate.actionId !== 'string'
  ) {
    return null;
  }

  return {
    energy: candidate.energy,
    energyVersion: candidate.energyVersion,
    actionId: candidate.actionId,
  };
}
