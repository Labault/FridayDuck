/**
 * Décisions PURES de l'UI de vote (§10.3) — testables sans DOM.
 */

export type VoteMode = 'open-can-vote' | 'open-voted' | 'closed';

export function voteMode(open: boolean, hasVoted: boolean): VoteMode {
  if (!open) {
    return 'closed';
  }

  return hasVoted ? 'open-voted' : 'open-can-vote';
}

/** Un vote n'est cliquable qu'ouvert+pas-voté, hors décompte expiré et hors requête en vol. */
export function canVote(mode: VoteMode, countdownExpired: boolean, inFlight: boolean): boolean {
  return 'open-can-vote' === mode && !countdownExpired && !inFlight;
}

export type OptionTally = {
  code: string;
  voteCount: number;
  displayOrder: number;
};

export function totalVotes(options: OptionTally[]): number {
  return options.reduce((sum, option) => sum + option.voteCount, 0);
}

/** Option en tête (§10.3) : plus de votes, départage par display_order. Null si aucun vote. */
export function leaderCode(options: OptionTally[]): string | null {
  if (totalVotes(options) === 0) {
    return null;
  }

  let leader: OptionTally | null = null;
  for (const option of options) {
    if (
      leader === null ||
      option.voteCount > leader.voteCount ||
      (option.voteCount === leader.voteCount && option.displayOrder < leader.displayOrder)
    ) {
      leader = option;
    }
  }

  return leader?.code ?? null;
}

export function percentage(voteCount: number, total: number): number {
  return 0 === total ? 0 : Math.round((voteCount / total) * 100);
}
