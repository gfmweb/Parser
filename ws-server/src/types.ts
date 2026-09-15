export interface ParseProgressPayload {
  organizationId: number;
  parseJobId: number;
  total: number;
  parsed: number;
  status: 'parsing' | 'done' | 'failed';
  error: string | null;
  name?: string | null;
  rating?: number | null;
  address?: string | null;
}

export interface WsMessage {
  type: 'subscribe' | 'progress' | 'error';
  channel: string;
  payload?: ParseProgressPayload;
  message?: string;
}

export function progressChannel(organizationId: number): string {
  return `parse.${organizationId}`;
}

export function organizationIdFromChannel(channel: string): number | null {
  const match = /^parse\.(\d+)$/.exec(channel);

  if (match?.[1] === undefined) {
    return null;
  }

  return Number.parseInt(match[1], 10);
}
