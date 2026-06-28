import { describe, expect, it, vi } from 'vitest';
import { CoffeeActionKey, postCoffee } from '../../assets/duck/coffee/coffeeClient.ts';

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const SUCCESS = {
  accepted: true,
  replayed: false,
  currentEnergy: 1,
  energyVersion: 1,
  remainingCoffeesForVisitor: 2,
  coffeeContributionId: 'C1',
};

describe('postCoffee', () => {
  it('sends the Idempotency-Key header and maps an acceptance', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, SUCCESS));

    const outcome = await postCoffee('/coffees', 'key-1', fetchMock);

    expect(fetchMock).toHaveBeenCalledWith(
      '/coffees',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ 'Idempotency-Key': 'key-1' }),
      }),
    );
    expect(outcome).toEqual({ type: 'ACCEPTED', energy: 1, energyVersion: 1, remainingCoffees: 2, contributionId: 'C1' });
  });

  it('maps an idempotent replay (200 with replayed: true)', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { ...SUCCESS, replayed: true }));

    expect(await postCoffee('/c', 'k', fetchMock)).toEqual({
      type: 'REPLAYED',
      energy: 1,
      energyVersion: 1,
      remainingCoffees: 2,
    });
  });

  it.each([
    [409, 'NOT_FRIDAY'],
    [429, 'LIMIT_REACHED'],
    [400, 'INVALID_KEY'],
  ])('maps HTTP %i → %s', async (status, type) => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse(status, { error: type }));

    expect((await postCoffee('/c', 'k', fetchMock)).type).toBe(type);
  });

  it('maps a network failure to NETWORK_ERROR (retriable)', async () => {
    const fetchMock = vi.fn().mockRejectedValue(new Error('offline'));

    expect((await postCoffee('/c', 'k', fetchMock)).type).toBe('NETWORK_ERROR');
  });
});

describe('CoffeeActionKey — same key on retry, freed at terminal result (§8.6)', () => {
  it('reuses the key across a retriable network error, then frees it', () => {
    const key = new CoffeeActionKey();
    let counter = 0;
    const generate = (): string => `k${++counter}`;

    expect(key.acquire(generate)).toBe('k1');

    // Échec réseau retriable → la MÊME clé est conservée pour le retry.
    key.settle({ type: 'NETWORK_ERROR' });
    expect(key.hasPending).toBe(true);
    expect(key.acquire(generate)).toBe('k1');

    // Résultat HTTP terminal → clé libérée → le prochain clic réel a une nouvelle clé.
    key.settle({ type: 'ACCEPTED', energy: 1, energyVersion: 1, remainingCoffees: 2, contributionId: 'C' });
    expect(key.hasPending).toBe(false);
    expect(key.acquire(generate)).toBe('k2');
  });
});
