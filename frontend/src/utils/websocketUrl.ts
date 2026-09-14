export function resolveWebSocketUrl(rawUrl = import.meta.env.VITE_WS_URL || '/ws'): string {
  if (rawUrl.startsWith('ws://') || rawUrl.startsWith('wss://')) {
    return rawUrl;
  }

  const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';

  if (rawUrl.startsWith('/')) {
    return `${protocol}//${window.location.host}${rawUrl}`;
  }

  return rawUrl;
}
