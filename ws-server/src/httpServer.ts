import { timingSafeEqual } from 'node:crypto';
import express, { type Express, type NextFunction, type Request, type Response } from 'express';
import type { Logger } from 'winston';
import { parseProgressPayloadSchema } from './schemas.js';
import type { Broadcaster } from './broadcaster.js';
import { progressChannel } from './types.js';

export interface HttpServerOptions {
  broadcaster: Broadcaster;
  internalSecret: string;
  getConnections: () => number;
  logger: Logger;
}

export function createHttpApp(options: HttpServerOptions): Express {
  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '32kb' }));

  app.get('/health', (_req: Request, res: Response) => {
    res.json({
      status: 'ok',
      connections: options.getConnections(),
    });
  });

  app.post('/internal/progress', (req: Request, res: Response) => {
    if (!isValidInternalSecret(req.header('x-internal-secret'), options.internalSecret)) {
      res.status(401).json({ message: 'Unauthorized', errors: {} });
      return;
    }

    const parsed = parseProgressPayloadSchema.safeParse(req.body);

    if (!parsed.success) {
      res.status(422).json({
        message: 'Validation failed',
        errors: parsed.error.flatten(),
      });
      return;
    }

    const payload = parsed.data;
    const channel = progressChannel(payload.organizationId);
    options.broadcaster.broadcast(channel, payload);

    res.json({ ok: true });
  });

  app.use((error: unknown, _req: Request, res: Response, next: NextFunction) => {
    if (res.headersSent) {
      next(error);
      return;
    }

    if (error instanceof SyntaxError) {
      res.status(422).json({
        message: 'Invalid JSON body',
        errors: { body: [error.message] },
      });
      return;
    }

    const err = error instanceof Error ? error : new Error(String(error));
    options.logger.error('http error', { message: err.message });
    res.status(500).json({ message: 'Internal server error', errors: {} });
  });

  return app;
}

function isValidInternalSecret(given: string | undefined, expected: string): boolean {
  if (given === undefined || given === '' || expected === '') {
    return false;
  }

  const givenBuffer = Buffer.from(given);
  const expectedBuffer = Buffer.from(expected);

  if (givenBuffer.length !== expectedBuffer.length) {
    return false;
  }

  return timingSafeEqual(givenBuffer, expectedBuffer);
}
