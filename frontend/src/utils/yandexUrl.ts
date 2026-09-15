const YANDEX_MAPS_URL_PATTERN = /yandex\.(ru|com)\/maps/i;

// Канонизация URL зеркалится в backend/app/Services/Parser/YandexUrlParser.php.

export function isYandexMapsUrl(url: string): boolean {
  return YANDEX_MAPS_URL_PATTERN.test(url.trim());
}

export function canonicalizeYandexMapsUrl(url: string): string {
  const trimmed = url.trim();

  try {
    const parsed = new URL(trimmed);
    parsed.search = '';
    parsed.hash = '';

    return parsed.toString();
  } catch {
    return trimmed.split('#')[0]?.split('?')[0] ?? trimmed;
  }
}

const ORG_ID_WITH_SLUG_PATTERN = /\/maps\/org\/[^/]+\/(\d+)(?:\/|$)/i;
const ORG_ID_ONLY_PATTERN = /\/maps\/org\/(\d+)(?:\/|$)/i;

export function extractYandexOrgId(url: string): string | null {
  const withSlug = url.match(ORG_ID_WITH_SLUG_PATTERN);

  if (withSlug?.[1] !== undefined) {
    return withSlug[1];
  }

  return url.match(ORG_ID_ONLY_PATTERN)?.[1] ?? null;
}

export function isDuplicateYandexOrganization(
  url: string,
  organizations: ReadonlyArray<{ yandex_id: string | null; yandex_url: string }>,
): boolean {
  const orgId = extractYandexOrgId(url);

  if (orgId === null) {
    return false;
  }

  return organizations.some(
    (organization) =>
      organization.yandex_id === orgId || extractYandexOrgId(organization.yandex_url) === orgId,
  );
}
