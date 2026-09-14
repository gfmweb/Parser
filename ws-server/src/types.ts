export interface ParseProgressPayload {
  organizationId: number;
  parseJobId: number;
  total: number;
  parsed: number;
  status: 'parsing' | 'done' | 'failed';
  error: string | null;
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
