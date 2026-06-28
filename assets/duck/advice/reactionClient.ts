/**
 * Client PUT /api/friday/current/advice-reaction (§24.4) + parsing du message
 * temps réel ADVICE_REACTION_CHANGED (§24.5). Pas de clé d'idempotence (UNIQUE
 * serveur ; PUT = upsert). Issues réconciliées (§19.4).
 */

export type ReactionCounts = {
  CONCERNING: number;
  ALREADY_DONE: number;
  TAKING_NOTES: number;
};

export type ReactionOutcome =
  | { type: 'RECORDED'; reaction: string; changed: boolean; adviceSequence: number; reactions: ReactionCounts }
  | { type: 'NOT_FRIDAY' }
  | { type: 'INVALID_REACTION' }
  | { type: 'NETWORK_ERROR' };

export type ReactionsMessage = {
  adviceSequence: number;
  reactions: ReactionCounts;
};

export type FetchLike = typeof fetch;

export async function putReaction(url: string, reaction: string, fetchImpl: FetchLike = fetch): Promise<ReactionOutcome> {
  let response: Response;
  try {
    response = await fetchImpl(url, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ reaction }),
    });
  } catch {
    return { type: 'NETWORK_ERROR' };
  }

  if (response.status === 200) {
    const body = (await response.json()) as { reaction: string; changed: boolean; adviceSequence: number; reactions: ReactionCounts };

    return { type: 'RECORDED', reaction: body.reaction, changed: body.changed, adviceSequence: body.adviceSequence, reactions: body.reactions };
  }

  if (response.status === 422) {
    return { type: 'INVALID_REACTION' };
  }

  if (response.status === 409) {
    const body = (await response.json()) as { reason?: string };

    return body.reason === 'NOT_FRIDAY' ? { type: 'NOT_FRIDAY' } : { type: 'NETWORK_ERROR' };
  }

  return { type: 'NETWORK_ERROR' };
}

export function parseReactionsMessage(raw: string): ReactionsMessage | null {
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
  if (typeof candidate.adviceSequence !== 'number' || !isCounts(candidate.reactions)) {
    return null;
  }

  return { adviceSequence: candidate.adviceSequence, reactions: candidate.reactions };
}

function isCounts(value: unknown): value is ReactionCounts {
  if (typeof value !== 'object' || value === null) {
    return false;
  }
  const counts = value as Record<string, unknown>;

  return typeof counts.CONCERNING === 'number' && typeof counts.ALREADY_DONE === 'number' && typeof counts.TAKING_NOTES === 'number';
}
