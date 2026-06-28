/**
 * Messages Mercure du vote (§24.5). Charge GLOBALE uniquement (invariant B) :
 * compteurs d'options et gagnant, jamais d'état par-visiteur (hasVoted vient du
 * serveur via le POST/GET, pas du flux).
 */

export type AccessoryOptionResult = {
  code: string;
  displayOrder: number;
  voteCount: number;
};

export type ResultsMessage = {
  resultsSequence: number;
  options: AccessoryOptionResult[];
};

export type WinnerMessage = {
  code: string;
  label: string;
  slot: string;
  svgGroupId: string;
};

export function parseResultsMessage(raw: string): ResultsMessage | null {
  const data = safeParse(raw);
  if (data === null) {
    return null;
  }
  if (typeof data.resultsSequence !== 'number' || !Array.isArray(data.options)) {
    return null;
  }

  const options: AccessoryOptionResult[] = [];
  for (const entry of data.options) {
    if (typeof entry !== 'object' || entry === null) {
      return null;
    }
    const option = entry as Record<string, unknown>;
    if (typeof option.code !== 'string' || typeof option.displayOrder !== 'number' || typeof option.voteCount !== 'number') {
      return null;
    }
    options.push({ code: option.code, displayOrder: option.displayOrder, voteCount: option.voteCount });
  }

  return { resultsSequence: data.resultsSequence, options };
}

export function parseWinnerMessage(raw: string): WinnerMessage | null {
  const data = safeParse(raw);
  if (data === null || typeof data.winner !== 'object' || data.winner === null) {
    return null;
  }

  return readWinner(data.winner as Record<string, unknown>);
}

/** Lit un objet gagnant brut (réutilisé pour le flux ET la réponse VOTE_CLOSED). */
export function readWinner(winner: Record<string, unknown>): WinnerMessage | null {
  if (
    typeof winner.code !== 'string' ||
    typeof winner.label !== 'string' ||
    typeof winner.slot !== 'string' ||
    typeof winner.svgGroupId !== 'string'
  ) {
    return null;
  }

  return { code: winner.code, label: winner.label, slot: winner.slot, svgGroupId: winner.svgGroupId };
}

function safeParse(raw: string): Record<string, unknown> | null {
  try {
    const data: unknown = JSON.parse(raw);

    return typeof data === 'object' && data !== null ? (data as Record<string, unknown>) : null;
  } catch {
    return null;
  }
}
