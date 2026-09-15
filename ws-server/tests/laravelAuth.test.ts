import { describe, expect, it, vi } from 'vitest';
import { createLaravelAccessChecker } from '../src/laravelAuth.js';

function jsonResponse(status: number, body: unknown = { id: 1 }): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('createLaravelAccessChecker', () => {
  it('allows subscribe when the token owns the organization', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (url.endsWith('/user')) {
        return jsonResponse(200, { id: 7 });
      }

      if (url.endsWith('/organizations/42')) {
        return jsonResponse(200, { id: 42 });
      }

      return jsonResponse(404, {});
    }) as typeof fetch;

    const checker = createLaravelAccessChecker({
      laravelApiUrl: 'http://nginx/api',
      fetchImpl,
    });

    await expect(checker.canAccess('valid-token', 42)).resolves.toBe(true);
    expect(fetchImpl).toHaveBeenNthCalledWith(
      1,
      'http://nginx/api/user',
      expect.objectContaining({
        redirect: 'error',
        headers: expect.objectContaining({
          Authorization: 'Bearer valid-token',
        }),
      }),
    );
    expect(fetchImpl).toHaveBeenNthCalledWith(
      2,
      'http://nginx/api/organizations/42',
      expect.objectContaining({
        redirect: 'error',
        headers: expect.objectContaining({
          Authorization: 'Bearer valid-token',
        }),
      }),
    );
  });

  it('denies HTML 200 from a misconfigured Laravel URL', async () => {
    const fetchImpl = vi.fn(
      async () =>
        new Response('<!doctype html><html></html>', {
          status: 200,
          headers: { 'Content-Type': 'text/html' },
        }),
    ) as typeof fetch;

    const checker = createLaravelAccessChecker({
      laravelApiUrl: 'http://nginx',
      fetchImpl,
    });

    await expect(checker.canAccess('valid-token', 42)).resolves.toBe(false);
  });

  it('denies a foreign organization with the same error path as a bad token', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse(200, { id: 7 }))
      .mockResolvedValueOnce(jsonResponse(403, {})) as typeof fetch;

    const checker = createLaravelAccessChecker({
      laravelApiUrl: 'http://nginx/api/',
      fetchImpl,
    });

    await expect(checker.canAccess('valid-token', 99)).resolves.toBe(false);
  });

  it('denies an invalid token without calling the organization endpoint', async () => {
    const fetchImpl = vi.fn(async () => jsonResponse(401, {})) as typeof fetch;
    const checker = createLaravelAccessChecker({
      laravelApiUrl: 'http://nginx/api',
      fetchImpl,
    });

    await expect(checker.canAccess('bad-token', 42)).resolves.toBe(false);
    expect(fetchImpl).toHaveBeenCalledTimes(1);
  });

  it('caches a successful check for 15 seconds', async () => {
    const fetchImpl = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);

      if (url.endsWith('/user')) {
        return jsonResponse(200, { id: 7 });
      }

      return jsonResponse(200, { id: 42 });
    }) as typeof fetch;
    let now = 1_000;
    const checker = createLaravelAccessChecker({
      laravelApiUrl: 'http://nginx/api',
      fetchImpl,
      now: () => now,
    });

    await expect(checker.canAccess('valid-token', 42)).resolves.toBe(true);
    await expect(checker.canAccess('valid-token', 42)).resolves.toBe(true);
    expect(fetchImpl).toHaveBeenCalledTimes(2);

    now = 16_000;
    await expect(checker.canAccess('valid-token', 42)).resolves.toBe(true);
    expect(fetchImpl).toHaveBeenCalledTimes(4);
  });

  it('fails closed when Laravel is unreachable', async () => {
    const fetchImpl = vi.fn(async () => {
      throw new Error('ECONNREFUSED');
    }) as typeof fetch;
    const checker = createLaravelAccessChecker({
      laravelApiUrl: 'http://nginx/api',
      fetchImpl,
    });

    await expect(checker.canAccess('valid-token', 42)).resolves.toBe(false);
  });
});
