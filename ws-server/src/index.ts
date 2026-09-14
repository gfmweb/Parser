import { createServer } from 'node:http';
import { Broadcaster } from './broadcaster.js';
import { createHttpApp } from './httpServer.js';
import { createLogger } from './logger.js';
import { attachWebSocketServer } from './wsServer.js';
import type { WebSocketServer } from 'ws';

const logger = createLogger();
const host = process.env.WS_HOST ?? '0.0.0.0';
const wsPort = readPort('WS_PORT', 6001);
const httpPort = readPort('HTTP_PORT', 6002);
const port = wsPort;
const internalSecret = process.env.WS_INTERNAL_SECRET ?? 'changeme';

const broadcaster = new Broadcaster(logger);
let wss: WebSocketServer | undefined;

const app = createHttpApp({
  broadcaster,
  internalSecret,
  getConnections: () => {
    const socketCount = wss?.clients.size ?? 0;
    const subscriberCount = broadcaster.getTotalSubscriberCount();

    return Math.max(socketCount, subscriberCount);
  },
  logger,
});

const httpServer = createServer(app);
wss = attachWebSocketServer(httpServer, broadcaster, logger);

httpServer.listen(port, host, () => {
  logger.info('ws-server started', {
    host,
    port,
    wsPort,
    httpPort,
    mode: 'shared-http-ws-upgrade',
  });
});

function readPort(name: string, fallback: number): number {
  const raw = process.env[name];

  if (raw === undefined || raw === '') {
    return fallback;
  }

  const value = Number.parseInt(raw, 10);

  if (!Number.isInteger(value) || value <= 0 || value > 65535) {
    throw new Error(`Invalid ${name}: ${raw}`);
  }

  return value;
}
