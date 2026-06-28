import { describe, expect, it, vi } from 'vitest';
import { EnergySubscription, type EventSourceLike } from '../../assets/duck/realtime/energySubscription.ts';

function fakeSource(): EventSourceLike & { closed: boolean; emit: (data: string) => void } {
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

describe('EnergySubscription (§19.5 cleanup)', () => {
  it('opens once and forwards raw message data', () => {
    const source = fakeSource();
    const sub = new EnergySubscription(() => source);
    const onMessage = vi.fn();

    sub.open('https://hub?topic=t', onMessage);
    expect(sub.isOpen).toBe(true);

    source.emit('{"energy":1}');
    expect(onMessage).toHaveBeenCalledWith('{"energy":1}');
  });

  it('does not open on an empty URL (dormant / no topic)', () => {
    const factory = vi.fn();
    const sub = new EnergySubscription(factory);

    sub.open('', () => {});

    expect(sub.isOpen).toBe(false);
    expect(factory).not.toHaveBeenCalled();
  });

  it('closes the EventSource on disconnect', () => {
    const source = fakeSource();
    const sub = new EnergySubscription(() => source);

    sub.open('https://hub?topic=t', () => {});
    sub.close();

    expect(source.closed).toBe(true);
    expect(sub.isOpen).toBe(false);
  });
});
