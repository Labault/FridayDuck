import { describe, expect, it, vi } from 'vitest';
import { SequenceBarrier } from '../../assets/duck/state/sequenceBarrier.ts';
import { parseResultsMessage, parseWinnerMessage } from '../../assets/duck/realtime/accessoryMessages.ts';
import { canVote, leaderCode, percentage, voteMode } from '../../assets/duck/vote/voteUiState.ts';
import { postVote } from '../../assets/duck/vote/voteClient.ts';

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

describe('SequenceBarrier (resultsSequence, §20.4)', () => {
  it('applies a strictly higher sequence and ignores ≤ current', () => {
    const barrier = new SequenceBarrier();
    expect(barrier.apply(0)).toBe(true);
    expect(barrier.apply(0)).toBe(false);
    expect(barrier.apply(-1)).toBe(false);
    expect(barrier.apply(1)).toBe(true);
    expect(barrier.value).toBe(1);
  });
});

describe('accessory message parsers (§24.5)', () => {
  it('parses results with options', () => {
    const raw = JSON.stringify({ type: 'ACCESSORY_RESULTS_UPDATED', resultsSequence: 4, options: [{ code: 'a', displayOrder: 1, voteCount: 2 }] });
    expect(parseResultsMessage(raw)).toEqual({ resultsSequence: 4, options: [{ code: 'a', displayOrder: 1, voteCount: 2 }] });
  });

  it('parses a winner object and rejects malformed ones', () => {
    const raw = JSON.stringify({ type: 'ACCESSORY_WINNER_SELECTED', winner: { code: 'cto_glasses', label: 'CTO', slot: 'head', svgGroupId: 'accessory-cto-glasses' } });
    expect(parseWinnerMessage(raw)).toEqual({ code: 'cto_glasses', label: 'CTO', slot: 'head', svgGroupId: 'accessory-cto-glasses' });
    expect(parseWinnerMessage(JSON.stringify({ winner: { code: 'x' } }))).toBeNull();
    expect(parseResultsMessage('not json')).toBeNull();
  });
});

describe('voteUiState (§10.3)', () => {
  it('derives the UI mode', () => {
    expect(voteMode(true, false)).toBe('open-can-vote');
    expect(voteMode(true, true)).toBe('open-voted');
    expect(voteMode(false, false)).toBe('closed');
  });

  it('only allows voting when open, not voted, not expired, not in flight', () => {
    expect(canVote('open-can-vote', false, false)).toBe(true);
    expect(canVote('open-can-vote', true, false)).toBe(false); // décompte expiré
    expect(canVote('open-can-vote', false, true)).toBe(false); // en vol
    expect(canVote('open-voted', false, false)).toBe(false);
    expect(canVote('closed', false, false)).toBe(false);
  });

  it('finds the leader and computes percentages', () => {
    const options = [
      { code: 'a', voteCount: 1, displayOrder: 1 },
      { code: 'b', voteCount: 3, displayOrder: 2 },
      { code: 'c', voteCount: 3, displayOrder: 3 },
    ];
    expect(leaderCode(options)).toBe('b'); // égalité → départage par display_order
    expect(leaderCode([{ code: 'a', voteCount: 0, displayOrder: 1 }])).toBeNull(); // aucun vote
    expect(percentage(3, 7)).toBe(43);
    expect(percentage(1, 0)).toBe(0);
  });
});

describe('postVote (§24.3) — reconciled outcomes', () => {
  it('maps an accepted vote', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { accepted: true, accessory: 'a', resultsSequence: 1 }));
    expect(await postVote('/votes', 'a', fetchMock)).toEqual({ type: 'ACCEPTED', accessory: 'a', resultsSequence: 1 });
  });

  it('maps 422 → INVALID_ACCESSORY', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(422, { accepted: false, reason: 'INVALID_ACCESSORY' }));
    expect((await postVote('/v', 'a', fetchMock)).type).toBe('INVALID_ACCESSORY');
  });

  it('maps 409 ALREADY_VOTED and VOTE_CLOSED (with winner)', async () => {
    const already = vi.fn().mockResolvedValue(jsonResponse(409, { accepted: false, reason: 'ALREADY_VOTED' }));
    expect((await postVote('/v', 'a', already)).type).toBe('ALREADY_VOTED');

    const closed = vi.fn().mockResolvedValue(jsonResponse(409, {
      accepted: false,
      reason: 'VOTE_CLOSED',
      winner: { code: 'w', label: 'W', slot: 'head', svgGroupId: 'accessory-w' },
    }));
    const outcome = await postVote('/v', 'a', closed);
    expect(outcome.type).toBe('VOTE_CLOSED');
    expect(outcome.type === 'VOTE_CLOSED' && outcome.winner?.code).toBe('w');
  });

  it('maps a network failure', async () => {
    const fetchMock = vi.fn().mockRejectedValue(new Error('offline'));
    expect((await postVote('/v', 'a', fetchMock)).type).toBe('NETWORK_ERROR');
  });
});
