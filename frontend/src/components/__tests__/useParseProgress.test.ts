import { defineComponent, nextTick, ref } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useParseProgress } from '@/composables/useParseProgress';
import type { ParseProgressPayload } from '@/types';

class FakeWebSocket extends EventTarget {
  readyState = 0;
  sent: string[] = [];

  constructor(public url: string) {
    super();
  }

  send(data: string): void {
    this.sent.push(data);
  }

  close(): void {
    if (this.readyState === 3) {
      return;
    }

    this.readyState = 3;
    this.dispatchEvent(new Event('close'));
  }

  open(): void {
    this.readyState = 1;
    this.dispatchEvent(new Event('open'));
  }

  emitMessage(data: string): void {
    this.dispatchEvent(new MessageEvent('message', { data }));
  }
}

function mountProgress(
  organizationId: number | { value: number | readonly number[] },
  sockets: FakeWebSocket[],
  wsUrl = 'ws://localhost/ws',
  token: string | null = 'test-token',
) {
  let api: ReturnType<typeof useParseProgress> | undefined;

  const host = defineComponent({
    setup() {
      api = useParseProgress(organizationId, {
        wsUrl,
        token,
        socketFactory: (url: string) => {
          const socket = new FakeWebSocket(url);
          sockets.push(socket);
          return socket as unknown as WebSocket;
        },
      });

      return () => null;
    },
  });

  const wrapper: VueWrapper = mount(host);

  if (api === undefined) {
    throw new Error('Composable was not initialized');
  }

  return { api, wrapper };
}

describe('useParseProgress', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('subscribes and updates progress from a WebSocket message', async () => {
    const sockets: FakeWebSocket[] = [];
    const { api, wrapper } = mountProgress(12, sockets);

    expect(sockets).toHaveLength(1);
    sockets[0]?.open();
    await nextTick();

    expect(api.isConnected.value).toBe(true);
    expect(sockets[0]?.sent).toEqual([
      JSON.stringify({ type: 'subscribe', channel: 'parse.12', token: 'test-token' }),
    ]);

    const payload: ParseProgressPayload = {
      organizationId: 12,
      parseJobId: 90,
      total: 580,
      parsed: 127,
      status: 'parsing',
      error: null,
    };

    sockets[0]?.emitMessage(
      JSON.stringify({
        type: 'progress',
        channel: 'parse.12',
        payload,
      }),
    );
    await nextTick();

    expect(api.progress.value).toEqual(payload);

    wrapper.unmount();
  });

  it('subscribes to every organization id on one socket', async () => {
    const sockets: FakeWebSocket[] = [];
    const ids = ref([17, 18]);
    const { wrapper } = mountProgress(ids, sockets);

    expect(sockets).toHaveLength(1);
    sockets[0]?.open();
    await nextTick();

    expect(sockets[0]?.sent).toEqual([
      JSON.stringify({ type: 'subscribe', channel: 'parse.17', token: 'test-token' }),
      JSON.stringify({ type: 'subscribe', channel: 'parse.18', token: 'test-token' }),
    ]);

    ids.value = [17, 18, 19];
    await nextTick();

    expect(sockets).toHaveLength(1);
    expect(sockets[0]?.sent).toEqual([
      JSON.stringify({ type: 'subscribe', channel: 'parse.17', token: 'test-token' }),
      JSON.stringify({ type: 'subscribe', channel: 'parse.18', token: 'test-token' }),
      JSON.stringify({ type: 'subscribe', channel: 'parse.19', token: 'test-token' }),
    ]);

    wrapper.unmount();
  });

  it('reconnects after disconnect with exponential backoff', async () => {
    const sockets: FakeWebSocket[] = [];
    const { api, wrapper } = mountProgress(3, sockets);

    sockets[0]?.open();
    await nextTick();
    expect(api.isConnected.value).toBe(true);

    sockets[0]?.close();
    await nextTick();
    expect(api.isConnected.value).toBe(false);
    expect(sockets).toHaveLength(1);

    await vi.advanceTimersByTimeAsync(500);
    expect(sockets).toHaveLength(2);

    sockets[1]?.close();
    await nextTick();
    expect(api.isConnected.value).toBe(false);

    await vi.advanceTimersByTimeAsync(999);
    expect(sockets).toHaveLength(2);

    await vi.advanceTimersByTimeAsync(1);
    expect(sockets).toHaveLength(3);

    wrapper.unmount();
  });

  it('does not subscribe without a token', async () => {
    const sockets: FakeWebSocket[] = [];
    const { wrapper } = mountProgress(12, sockets, 'ws://localhost/ws', null);

    sockets[0]?.open();
    await nextTick();

    expect(sockets[0]?.sent).toEqual([]);

    wrapper.unmount();
  });
});
