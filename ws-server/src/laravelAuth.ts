import { createHash } from 'node:crypto';

export interface OrganizationAccessChecker {
  canAccess(token: string, organizationId: number): Promise<boolean>;
}

export interface LaravelAccessCheckerOptions {
  laravelApiUrl: string;
  fetchImpl?: typeof fetch;
  now?: () => number;
  ttlMs?: number;
  timeoutMs?: number;
}

const DEFAULT_TTL_MS = 60_000;
const DEFAULT_TIMEOUT_MS = 5_000;

export function createLaravelAccessChecker(
  options: LaravelAccessCheckerOptions,
): OrganizationAccessChecker {
  const fetchImpl = options.fetchImpl ?? fetch;
  const now = options.now ?? Date.now;
  const ttlMs = options.ttlMs ?? DEFAULT_TTL_MS;
  const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  const baseUrl = options.laravelApiUrl.replace(/\/+$/, '');
  const cache = new Map<string, number>();

  return {
    async canAccess(token: string, organizationId: number): Promise<boolean> {
      if (token === '') {
        return false;
      }

      const key = cacheKey(token, organizationId);
      const expiresAt = cache.get(key);

      if (expiresAt !== undefined && expiresAt > now()) {
        return true;
      }

      cache.delete(key);

      try {
        const userStatus = await requestStatus(fetchImpl, `${baseUrl}/user`, token, timeoutMs);

        if (userStatus !== 200) {
          return false;
        }

        const organizationStatus = await requestStatus(
          fetchImpl,
          `${baseUrl}/organizations/${organizationId}`,
          token,
          timeoutMs,
        );

        if (organizationStatus !== 200) {
          return false;
        }
      } catch {
        return false;
      }

      cache.set(key, now() + ttlMs);

      return true;
    },
  };
}

function cacheKey(token: string, organizationId: number): string {
  return `${createHash('sha256').update(token).digest('hex')}:${organizationId}`;
}

async function requestStatus(
  fetchImpl: typeof fetch,
  url: string,
  token: string,
  timeoutMs: number,
): Promise<number> {
  const response = await fetchImpl(url, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    signal: AbortSignal.timeout(timeoutMs),
  });

  return response.status;
}
