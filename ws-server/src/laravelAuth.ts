import { createHash } from 'node:crypto';

export interface OrganizationAccessChecker {
  canAccess(token: string, organizationId: number): Promise<boolean>;
}

export interface LaravelAccessCheckerOptions {
  laravelApiUrl: string;
  fetchImpl?: typeof fetch;
  now?: () => number;
  ttlMs?: number;
  negativeTtlMs?: number;
  timeoutMs?: number;
}

const DEFAULT_TTL_MS = 15_000;
const DEFAULT_NEGATIVE_TTL_MS = 5_000;
const DEFAULT_TIMEOUT_MS = 5_000;

interface CacheEntry {
  allowed: boolean;
  expiresAt: number;
}

export function createLaravelAccessChecker(
  options: LaravelAccessCheckerOptions,
): OrganizationAccessChecker {
  const fetchImpl = options.fetchImpl ?? fetch;
  const now = options.now ?? Date.now;
  const ttlMs = options.ttlMs ?? DEFAULT_TTL_MS;
  const negativeTtlMs = options.negativeTtlMs ?? DEFAULT_NEGATIVE_TTL_MS;
  const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  const baseUrl = options.laravelApiUrl.replace(/\/+$/, '');
  const cache = new Map<string, CacheEntry>();

  return {
    async canAccess(token: string, organizationId: number): Promise<boolean> {
      if (token === '') {
        return false;
      }

      const key = cacheKey(token, organizationId);
      const cached = cache.get(key);

      if (cached !== undefined && cached.expiresAt > now()) {
        return cached.allowed;
      }

      cache.delete(key);

      try {
        const user = await requestJson(fetchImpl, `${baseUrl}/user`, token, timeoutMs);

        if (user === null || !hasPositiveId(user)) {
          cache.set(key, { allowed: false, expiresAt: now() + negativeTtlMs });
          return false;
        }

        const organization = await requestJson(
          fetchImpl,
          `${baseUrl}/organizations/${organizationId}`,
          token,
          timeoutMs,
        );

        if (organization === null || !hasId(organization, organizationId)) {
          cache.set(key, { allowed: false, expiresAt: now() + negativeTtlMs });
          return false;
        }
      } catch {
        return false;
      }

      cache.set(key, { allowed: true, expiresAt: now() + ttlMs });

      return true;
    },
  };
}

function cacheKey(token: string, organizationId: number): string {
  return `${createHash('sha256').update(token).digest('hex')}:${organizationId}`;
}

async function requestJson(
  fetchImpl: typeof fetch,
  url: string,
  token: string,
  timeoutMs: number,
): Promise<unknown | null> {
  const response = await fetchImpl(url, {
    method: 'GET',
    redirect: 'error',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    signal: AbortSignal.timeout(timeoutMs),
  });

  if (response.status !== 200) {
    return null;
  }

  const contentType = response.headers.get('content-type') ?? '';

  if (!contentType.toLowerCase().includes('application/json')) {
    return null;
  }

  try {
    return (await response.json()) as unknown;
  } catch {
    return null;
  }
}

function hasPositiveId(value: unknown): boolean {
  if (typeof value !== 'object' || value === null || !('id' in value)) {
    return false;
  }

  const id = value.id;

  return typeof id === 'number' && Number.isInteger(id) && id > 0;
}

function hasId(value: unknown, expectedId: number): boolean {
  if (typeof value !== 'object' || value === null || !('id' in value)) {
    return false;
  }

  return value.id === expectedId;
}
