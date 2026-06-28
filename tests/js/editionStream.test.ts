import { describe, expect, it, vi } from 'vitest';
import { EditionStream } from '../../assets/duck/realtime/editionStream.ts';
import type { EventSourceLike } from '../../assets/duck/realtime/energySubscription.ts';

type FakeSource = EventSourceLike & { closed: boolean; emit: (data: string) => void };

function fakeSource(): FakeSource {
  let listener: ((event: MessageEvent) => void) | null = null;
  return {
    closed: false,
    addEventListener(_type, l) {
      listener = l;
    },
    close() {
      this.closed = true;
    },
    emit(data: string) {
      listener?.({ data } as MessageEvent);
    },
  };
}

describe('EditionStream — one connection, demux by type, ref-count (§19.5, §24.5)', () => {
  it('opens a SINGLE EventSource shared by multiple subscribers', () => {
    const factory = vi.fn(() => fakeSource());
    const stream = new EditionStream(factory);

    const a = stream.connect('https://hub?topic=t');
    const b = stream.connect('https://hub?topic=t');

    expect(factory).toHaveBeenCalledTimes(1);
    expect(stream.isConnected).toBe(true);
    a.close();
    b.close();
  });

  it('demultiplexes messages by `type` to the right handlers', () => {
    const source = fakeSource();
    const stream = new EditionStream(() => source);
    const onEnergy = vi.fn();
    const onWinner = vi.fn();

    const sub = stream.connect('u');
    sub.on('ENERGY_CHANGED', onEnergy);
    sub.on('ACCESSORY_WINNER_SELECTED', onWinner);

    source.emit(JSON.stringify({ type: 'ENERGY_CHANGED', energy: 1 }));
    source.emit(JSON.stringify({ type: 'ACCESSORY_WINNER_SELECTED', winner: {} }));
    source.emit(JSON.stringify({ type: 'UNREGISTERED' }));
    source.emit('not json');

    expect(onEnergy).toHaveBeenCalledTimes(1);
    expect(onWinner).toHaveBeenCalledTimes(1);
    sub.close();
  });

  it('closes the EventSource only at the LAST unsubscribe (ref-counting)', () => {
    const source = fakeSource();
    const stream = new EditionStream(() => source);

    const a = stream.connect('u');
    const b = stream.connect('u');

    a.close();
    expect(source.closed).toBe(false); // l'autre contrôleur est encore abonné
    expect(stream.isConnected).toBe(true);

    b.close();
    expect(source.closed).toBe(true); // dernier désabonnement → fermé
    expect(stream.isConnected).toBe(false);
  });

  it('reopens after everyone left and a new subscriber connects', () => {
    const factory = vi.fn(() => fakeSource());
    const stream = new EditionStream(factory);

    stream.connect('u').close();
    stream.connect('u');

    expect(factory).toHaveBeenCalledTimes(2);
  });

  it('does not open on an empty URL (no topic)', () => {
    const factory = vi.fn();
    const stream = new EditionStream(factory);

    stream.connect('');

    expect(factory).not.toHaveBeenCalled();
    expect(stream.isConnected).toBe(false);
  });
});
