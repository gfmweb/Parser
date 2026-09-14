import { WebSocket } from 'ws';
import { describe, expect, it, vi } from 'vitest';
import { Broadcaster } from '../src/broadcaster.js';
import { createSilentLogger } from '../src/logger.js';
import type { ParseProgressPayload } from '../src/types.js';

function createMockSocket(): WebSocket {
  return {
    readyState: WebSocket.OPEN,
    send: vi.fn(),
  } as unknown as WebSocket;
}

function progressPayload(organizationId = 42): ParseProgressPayload {
  return {
    organizationId,
    parseJobId: 11,
    total: 20,
    parsed: 5,
    status: 'parsing',
    error: null,
  };
}

describe('Broadcaster', () => {
  it('adds a client to the channel on subscribe', () => {
    const broadcaster = new Broadcaster(createSilentLogger());
    const socket = createMockSocket();

    broadcaster.subscribe('parse.42', socket);

    expect(broadcaster.getSubscriberCount('parse.42')).toBe(1);
  });

  it('sends broadcast only to clients subscribed to that channel', () => {
    const broadcaster = new Broadcaster(createSilentLogger());
    const org42 = createMockSocket();
    const org99 = createMockSocket();
    const payload = progressPayload(42);

    broadcaster.subscribe('parse.42', org42);
    broadcaster.subscribe('parse.99', org99);
    broadcaster.broadcast('parse.42', payload);

    expect(org42.send).toHaveBeenCalledTimes(1);
    expect(org42.send).toHaveBeenCalledWith(
      JSON.stringify({
        type: 'progress',
        channel: 'parse.42',
        payload,
      }),
    );
    expect(org99.send).not.toHaveBeenCalled();
  });

  it('removes a client from all channels on unsubscribe', () => {
    const broadcaster = new Broadcaster(createSilentLogger());
    const socket = createMockSocket();

    broadcaster.subscribe('parse.42', socket);
    broadcaster.subscribe('parse.99', socket);
    broadcaster.unsubscribe(socket);

    expect(broadcaster.getSubscriberCount('parse.42')).toBe(0);
    expect(broadcaster.getSubscriberCount('parse.99')).toBe(0);
    expect(broadcaster.getTotalSubscriberCount()).toBe(0);

    broadcaster.broadcast('parse.42', progressPayload(42));
    expect(socket.send).not.toHaveBeenCalled();
  });

  it('does not throw when broadcasting to an empty channel', () => {
    const broadcaster = new Broadcaster(createSilentLogger());

    expect(() => broadcaster.broadcast('parse.42', progressPayload(42))).not.toThrow();
    expect(broadcaster.getSubscriberCount('parse.42')).toBe(0);
  });

  it('logs channel name and subscriber count on every broadcast', () => {
    const logger = createSilentLogger();
    const info = vi.spyOn(logger, 'info');
    const broadcaster = new Broadcaster(logger);

    broadcaster.subscribe('parse.42', createMockSocket());
    broadcaster.broadcast('parse.42', progressPayload(42));

    expect(info).toHaveBeenCalledWith(
      'broadcast',
      expect.objectContaining({
        channel: 'parse.42',
        subscribers: 1,
      }),
    );
  });
});
