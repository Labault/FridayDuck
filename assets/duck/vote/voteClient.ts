import { readWinner, type WinnerMessage } from '../realtime/accessoryMessages.ts';

/**
 * Client POST /api/friday/current/accessory-votes (§24.3). PAS de clé
 * d'idempotence : le vote est déjà UNIQUE(edition, visitor) côté serveur — un
 * re-clic renvoie ALREADY_VOTED. Issues normalisées et réconciliées (§19.4).
 */
export type VoteOutcome =
  | { type: 'ACCEPTED'; accessory: string; resultsSequence: number }
  | { type: 'ALREADY_VOTED' }
  | { type: 'VOTE_CLOSED'; winner: WinnerMessage | null }
  | { type: 'INVALID_ACCESSORY' }
  | { type: 'NOT_FRIDAY' }
  | { type: 'NETWORK_ERROR' };

export type FetchLike = typeof fetch;

export async function postVote(url: string, accessoryCode: string, fetchImpl: FetchLike = fetch): Promise<VoteOutcome> {
  let response: Response;
  try {
    response = await fetchImpl(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ accessory: accessoryCode }),
    });
  } catch {
    return { type: 'NETWORK_ERROR' };
  }

  if (response.status === 200) {
    const body = (await response.json()) as { accessory: string; resultsSequence: number };

    return { type: 'ACCEPTED', accessory: body.accessory, resultsSequence: body.resultsSequence };
  }

  if (response.status === 422) {
    return { type: 'INVALID_ACCESSORY' };
  }

  if (response.status === 409) {
    const body = (await response.json()) as { reason?: string; winner?: unknown };
    switch (body.reason) {
      case 'VOTE_CLOSED':
        return { type: 'VOTE_CLOSED', winner: typeof body.winner === 'object' && body.winner !== null ? readWinner(body.winner as Record<string, unknown>) : null };
      case 'ALREADY_VOTED':
        return { type: 'ALREADY_VOTED' };
      case 'NOT_FRIDAY':
        return { type: 'NOT_FRIDAY' };
      default:
        return { type: 'NETWORK_ERROR' };
    }
  }

  return { type: 'NETWORK_ERROR' };
}
