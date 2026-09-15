import { WebSocket } from 'ws';
import { describe, expect, it, vi } from 'vitest';
import { Broadcaster } from '../src/broadcaster.js';
import { createSilentLogger } from '../src/logger.js';
import { handleClientMessage } from '../src/wsServer.js';

function createMockSocket(): WebSocket {
  return {
    readyState: WebSocket.OPEN,
    send: vi.fn(),
    close: vi.fn(),
  } as unknown as WebSocket;
}

describe('handleClientMessage', () => {
  it('subscribes when Laravel allows the organization', async () => {
    const broadcaster = new Broadcaster(createSilentLogger());
    const socket = createMockSocket();
    const accessChecker = {
      canAccess: vi.fn().mockResolvedValue(true),
    };

    await handleClientMessage(
      socket,
      Buffer.from(JSON.stringify({ type: 'subscribe', channel: 'parse.12', token: 'secret-token' })),
      broadcaster,
      createSilentLogger(),
      accessChecker,
    );

    expect(accessChecker.canAccess).toHaveBeenCalledWith('secret-token', 12);
    expect(broadcaster.getSubscriberCount('parse.12')).toBe(1);
    expect(socket.close).not.toHaveBeenCalled();
  });

  it('rejects a foreign organization without subscribing', async () => {
    const broadcaster = new Broadcaster(createSilentLogger());
    const socket = createMockSocket();
    const accessChecker = {
      canAccess: vi.fn().mockResolvedValue(false),
    };

    await handleClientMessage(
      socket,
      Buffer.from(JSON.stringify({ type: 'subscribe', channel: 'parse.12', token: 'secret-token' })),
      broadcaster,
      createSilentLogger(),
      accessChecker,
    );

    expect(broadcaster.getSubscriberCount('parse.12')).toBe(0);
    expect(socket.send).toHaveBeenCalledWith(
      JSON.stringify({ type: 'error', channel: 'parse.12', message: 'Forbidden' }),
    );
    expect(socket.close).not.toHaveBeenCalled();
  });

  it('closes the socket when the subscribe payload has no token', async () => {
    const broadcaster = new Broadcaster(createSilentLogger());
    const socket = createMockSocket();
    const accessChecker = {
      canAccess: vi.fn(),
    };

    await handleClientMessage(
      socket,
      Buffer.from(JSON.stringify({ type: 'subscribe', channel: 'parse.12' })),
      broadcaster,
      createSilentLogger(),
      accessChecker,
    );

    expect(accessChecker.canAccess).not.toHaveBeenCalled();
    expect(broadcaster.getSubscriberCount('parse.12')).toBe(0);
    expect(socket.send).toHaveBeenCalledWith(
      JSON.stringify({ type: 'error', channel: 'unknown', message: 'Invalid subscribe message' }),
    );
    expect(socket.close).toHaveBeenCalled();
  });
});
