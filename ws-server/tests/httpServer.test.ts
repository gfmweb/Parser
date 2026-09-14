import request from 'supertest';
import { describe, expect, it, vi } from 'vitest';
import { Broadcaster } from '../src/broadcaster.js';
import { createHttpApp } from '../src/httpServer.js';
import { createSilentLogger } from '../src/logger.js';
import type { ParseProgressPayload } from '../src/types.js';

const secret = 'test-secret';

function payload(): ParseProgressPayload {
  return {
    organizationId: 42,
    parseJobId: 11,
    total: 20,
    parsed: 5,
    status: 'parsing',
    error: null,
  };
}

function createApp(broadcaster = new Broadcaster(createSilentLogger()), connections = 0) {
  return createHttpApp({
    broadcaster,
    internalSecret: secret,
    getConnections: () => connections,
    logger: createSilentLogger(),
  });
}

describe('HTTP internal progress', () => {
  it('returns 401 when the internal secret is wrong', async () => {
    const response = await request(createApp())
      .post('/internal/progress')
      .set('x-internal-secret', 'wrong')
      .send(payload());

    expect(response.status).toBe(401);
    expect(response.body).toMatchObject({ message: 'Unauthorized' });
  });

  it('returns 422 with zod error details for an invalid body', async () => {
    const response = await request(createApp())
      .post('/internal/progress')
      .set('x-internal-secret', secret)
      .send({ organizationId: 'not-a-number' });

    expect(response.status).toBe(422);
    expect(response.body).toMatchObject({ message: 'Validation failed' });
    expect(response.body.errors).toBeDefined();
    expect(response.body.errors.fieldErrors).toBeDefined();
  });

  it('broadcasts to parse.{organizationId} and returns ok', async () => {
    const broadcaster = new Broadcaster(createSilentLogger());
    const spy = vi.spyOn(broadcaster, 'broadcast');
    const body = payload();

    const response = await request(createApp(broadcaster))
      .post('/internal/progress')
      .set('x-internal-secret', secret)
      .send(body);

    expect(response.status).toBe(200);
    expect(response.body).toEqual({ ok: true });
    expect(spy).toHaveBeenCalledWith('parse.42', body);
  });

  it('returns subscriber count on health', async () => {
    const response = await request(createApp(new Broadcaster(createSilentLogger()), 3)).get('/health');

    expect(response.status).toBe(200);
    expect(response.body).toEqual({ status: 'ok', connections: 3 });
  });
});
