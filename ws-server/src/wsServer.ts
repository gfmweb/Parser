import type { IncomingMessage, Server as HttpServer } from 'node:http';
import type { Logger } from 'winston';
import { type RawData, WebSocket, WebSocketServer } from 'ws';
import type { Broadcaster } from './broadcaster.js';
import type { OrganizationAccessChecker } from './laravelAuth.js';
import { clientSubscribeMessageSchema } from './schemas.js';
import { organizationIdFromChannel, type WsMessage } from './types.js';

export function attachWebSocketServer(
  httpServer: HttpServer,
  broadcaster: Broadcaster,
  logger: Logger,
  accessChecker: OrganizationAccessChecker,
): WebSocketServer {
  const wss = new WebSocketServer({ server: httpServer });

  wss.on('connection', (ws: WebSocket, _request: IncomingMessage) => {
    logger.info('ws connected', { connections: wss.clients.size });

    ws.on('message', (raw: RawData) => {
      void handleClientMessage(ws, raw, broadcaster, logger, accessChecker);
    });

    ws.on('close', () => {
      broadcaster.unsubscribe(ws);
      logger.info('ws disconnected', { connections: wss.clients.size });
    });

    ws.on('error', (error: Error) => {
      logger.warn('ws error', { message: error.message });
      broadcaster.unsubscribe(ws);
    });
  });

  return wss;
}

export async function handleClientMessage(
  ws: WebSocket,
  raw: RawData,
  broadcaster: Broadcaster,
  logger: Logger,
  accessChecker: OrganizationAccessChecker,
): Promise<void> {
  const text = toText(raw);
  let parsedJson: unknown;

  try {
    parsedJson = JSON.parse(text) as unknown;
  } catch {
    rejectClient(ws, 'Invalid JSON');
    return;
  }

  const parsed = clientSubscribeMessageSchema.safeParse(parsedJson);

  if (!parsed.success) {
    rejectClient(ws, 'Invalid subscribe message');
    return;
  }

  const organizationId = organizationIdFromChannel(parsed.data.channel);

  if (organizationId === null) {
    sendError(ws, parsed.data.channel, 'Forbidden');
    return;
  }

  const allowed = await accessChecker.canAccess(parsed.data.token, organizationId);

  if (!allowed) {
    sendError(ws, parsed.data.channel, 'Forbidden');
    logger.info('ws subscribe denied', { channel: parsed.data.channel });
    return;
  }

  broadcaster.subscribe(parsed.data.channel, ws);
  logger.info('ws subscribed', {
    channel: parsed.data.channel,
    subscribers: broadcaster.getSubscriberCount(parsed.data.channel),
  });
}

function sendError(ws: WebSocket, channel: string, message: string): void {
  const errorMessage: WsMessage = {
    type: 'error',
    channel,
    message,
  };

  if (ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify(errorMessage));
  }
}

function rejectClient(ws: WebSocket, message: string): void {
  sendError(ws, 'unknown', message);

  if (ws.readyState === WebSocket.OPEN) {
    ws.close();
    return;
  }

  ws.close();
}

function toText(raw: RawData): string {
  if (Buffer.isBuffer(raw)) {
    return raw.toString('utf8');
  }

  if (Array.isArray(raw)) {
    return Buffer.concat(raw).toString('utf8');
  }

  if (raw instanceof ArrayBuffer) {
    return Buffer.from(raw).toString('utf8');
  }

  return Buffer.from(raw).toString('utf8');
}
