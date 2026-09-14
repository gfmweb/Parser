import { onUnmounted, ref, toValue, watch, type MaybeRefOrGetter, type Ref } from 'vue';
import type { ParseProgressPayload } from '@/types';
import { resolveWebSocketUrl } from '@/utils/websocketUrl';

const MAX_RECONNECT_ATTEMPTS = 5;
const RECONNECT_BASE_MS = 500;

export interface ParseProgressOptions {
  socketFactory?: (url: string) => WebSocket;
  wsUrl?: string;
}

interface IncomingProgressMessage {
  type: 'progress';
  channel: string;
  payload?: ParseProgressPayload;
}

export function useParseProgress(
  organizationId: MaybeRefOrGetter<number>,
  options: ParseProgressOptions = {},
): { progress: Ref<ParseProgressPayload | null>; isConnected: Ref<boolean> } {
  const progress = ref<ParseProgressPayload | null>(null);
  const isConnected = ref(false);

  let socket: WebSocket | null = null;
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  let attempts = 0;
  let generation = 0;
  let stopped = false;

  function clearReconnectTimer(): void {
    if (reconnectTimer !== null) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
  }

  function teardownSocket(): void {
    if (socket === null) {
      return;
    }

    socket.onopen = null;
    socket.onmessage = null;
    socket.onerror = null;
    socket.onclose = null;

    if (socket.readyState === 0 || socket.readyState === 1) {
      socket.close();
    }

    socket = null;
    isConnected.value = false;
  }

  function handleMessage(raw: string): void {
    let parsed: unknown;

    try {
      parsed = JSON.parse(raw) as unknown;
    } catch {
      return;
    }

    if (!isProgressMessage(parsed) || parsed.payload === undefined) {
      return;
    }

    progress.value = parsed.payload;
  }

  function scheduleReconnect(): void {
    if (stopped || attempts >= MAX_RECONNECT_ATTEMPTS) {
      return;
    }

    const delay = RECONNECT_BASE_MS * 2 ** attempts;
    attempts += 1;
    reconnectTimer = setTimeout(() => {
      connect();
    }, delay);
  }

  function connect(): void {
    if (stopped) {
      return;
    }

    const id = toValue(organizationId);

    if (!Number.isInteger(id) || id <= 0) {
      return;
    }

    generation += 1;
    const currentGeneration = generation;
    clearReconnectTimer();
    teardownSocket();

    const factory = options.socketFactory ?? ((url: string) => new WebSocket(url));
    const nextSocket = factory(options.wsUrl ?? resolveWebSocketUrl());
    socket = nextSocket;

    nextSocket.addEventListener('open', () => {
      if (stopped || currentGeneration !== generation || socket !== nextSocket) {
        return;
      }

      attempts = 0;
      isConnected.value = true;
      nextSocket.send(
        JSON.stringify({
          type: 'subscribe',
          channel: `parse.${id}`,
        }),
      );
    });

    nextSocket.addEventListener('message', (event: Event) => {
      if (currentGeneration !== generation || socket !== nextSocket) {
        return;
      }

      if (!(event instanceof MessageEvent) || typeof event.data !== 'string') {
        return;
      }

      handleMessage(event.data);
    });

    nextSocket.addEventListener('close', () => {
      if (currentGeneration !== generation) {
        return;
      }

      isConnected.value = false;
      socket = null;
      scheduleReconnect();
    });
  }

  watch(
    () => toValue(organizationId),
    () => {
      stopped = false;
      attempts = 0;
      progress.value = null;
      connect();
    },
    { immediate: true },
  );

  onUnmounted(() => {
    stopped = true;
    generation += 1;
    clearReconnectTimer();
    teardownSocket();
  });

  return { progress, isConnected };
}

function isProgressMessage(value: unknown): value is IncomingProgressMessage {
  if (typeof value !== 'object' || value === null || !('type' in value)) {
    return false;
  }

  return value.type === 'progress';
}
