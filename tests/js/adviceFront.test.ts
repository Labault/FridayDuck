import { describe, expect, it, vi } from 'vitest';
import { parseReactionsMessage, putReaction } from '../../assets/duck/advice/reactionClient.ts';
import { SequenceBarrier } from '../../assets/duck/state/sequenceBarrier.ts';

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

const COUNTS = { CONCERNING: 2, ALREADY_DONE: 1, TAKING_NOTES: 0 };

describe('putReaction (§24.4) — reconciled outcomes', () => {
  it('maps a recorded change', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { accepted: true, changed: true, reaction: 'CONCERNING', adviceSequence: 1, reactions: COUNTS }));

    const outcome = await putReaction('/advice', 'CONCERNING', fetchMock);

    expect(fetchMock).toHaveBeenCalledWith('/advice', expect.objectContaining({ method: 'PUT' }));
    expect(outcome).toEqual({ type: 'RECORDED', changed: true, reaction: 'CONCERNING', adviceSequence: 1, reactions: COUNTS });
  });

  it('maps a no-op (changed: false)', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { accepted: true, changed: false, reaction: 'CONCERNING', adviceSequence: 1, reactions: COUNTS }));

    const outcome = await putReaction('/advice', 'CONCERNING', fetchMock);

    expect(outcome.type === 'RECORDED' && outcome.changed).toBe(false);
  });

  it('maps 422 → INVALID_REACTION, 409 NOT_FRIDAY, network error', async () => {
    expect((await putReaction('/a', 'X', vi.fn().mockResolvedValue(jsonResponse(422, { reason: 'INVALID_REACTION' })))).type).toBe('INVALID_REACTION');
    expect((await putReaction('/a', 'X', vi.fn().mockResolvedValue(jsonResponse(409, { reason: 'NOT_FRIDAY' })))).type).toBe('NOT_FRIDAY');
    expect((await putReaction('/a', 'X', vi.fn().mockRejectedValue(new Error('offline')))).type).toBe('NETWORK_ERROR');
  });
});

describe('parseReactionsMessage (§24.5) + advice sequence barrier', () => {
  it('parses a well-formed message and rejects malformed ones', () => {
    const raw = JSON.stringify({ type: 'ADVICE_REACTION_CHANGED', adviceSequence: 3, reactions: COUNTS });
    expect(parseReactionsMessage(raw)).toEqual({ adviceSequence: 3, reactions: COUNTS });
    expect(parseReactionsMessage(JSON.stringify({ adviceSequence: 3 }))).toBeNull();
    expect(parseReactionsMessage('not json')).toBeNull();
  });

  it('the advice barrier applies a higher sequence and ignores stale ones', () => {
    const barrier = new SequenceBarrier();
    expect(barrier.apply(3)).toBe(true);
    expect(barrier.apply(3)).toBe(false);
    expect(barrier.apply(2)).toBe(false);
    expect(barrier.apply(4)).toBe(true);
  });
});
