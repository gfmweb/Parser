import { onUnmounted, ref, toValue, watch, type MaybeRefOrGetter, type Ref } from 'vue';
import { getStoredToken } from '@/api/token';
import type { ParseProgressPayload } from '@/types';
import { resolveWebSocketUrl } from '@/utils/websocketUrl';

const MAX_RECONNECT_ATTEMPTS = 5;
const RECONNECT_BASE_MS = 500;

export interface ParseProgressOptions {
  socketFactory?: (url: string) => WebSocket;
  wsUrl?: string;
  token?: MaybeRefOrGetter<string | null>;
}

interface IncomingProgressMessage {
  type: 'progress';
  channel: string;
  payload?: ParseProgressPayload;
}

export function useParseProgress(
  organizationIds: MaybeRefOrGetter<number | readonly number[]>,
  options: ParseProgressOptions = {},
): { progress: Ref<ParseProgressPayload | null>; isConnected: Ref<boolean> } {
  const progress = ref<ParseProgressPayload | null>(null);
  const isConnected = ref(false);

  let socket: WebSocket | null = null;
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  let attempts = 0;
  let generation = 0;
  let stopped = false;
  const subscribed = new Set<number>();

  function currentIds(): number[] {
    return normalizeOrganizationIds(toValue(organizationIds));
  }

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
    subscribed.clear();
  }

  function currentToken(): string | null {
    const value = options.token === undefined ? getStoredToken() : toValue(options.token);

    if (value === null || value === '') {
      return null;
    }

    return value;
  }

  function subscribeChannels(target: WebSocket, ids: number[]): void {
    const token = currentToken();

    if (token === null) {
      return;
    }

    for (const id of ids) {
      if (subscribed.has(id)) {
        continue;
      }

      target.send(
        JSON.stringify({
          type: 'subscribe',
          channel: `parse.${id}`,
          token,
        }),
      );
      subscribed.add(id);
    }
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

    const ids = currentIds();

    if (ids.length === 0) {
      teardownSocket();
      return;
    }

    if (socket !== null && socket.readyState === 1) {
      subscribeChannels(socket, ids);
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
      subscribed.clear();
      subscribeChannels(nextSocket, currentIds());
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
      subscribed.clear();
      scheduleReconnect();
    });
  }

  watch(
    () => normalizeOrganizationIds(toValue(organizationIds)).join(','),
    () => {
      stopped = false;
      const ids = currentIds();

      if (progress.value !== null && !ids.includes(progress.value.organizationId)) {
        progress.value = null;
      }

      if (ids.length === 0) {
        attempts = 0;
        teardownSocket();
        return;
      }

      if (socket !== null && socket.readyState === 1) {
        subscribeChannels(socket, ids);
        return;
      }

      attempts = 0;
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

export function normalizeOrganizationIds(value: number | readonly number[]): number[] {
  const list = typeof value === 'number' ? [value] : [...value];
  const unique = new Set<number>();

  for (const id of list) {
    if (Number.isInteger(id) && id > 0) {
      unique.add(id);
    }
  }

  return [...unique];
}

function isProgressMessage(value: unknown): value is IncomingProgressMessage {
  if (typeof value !== 'object' || value === null || !('type' in value)) {
    return false;
  }

  return value.type === 'progress';
}
