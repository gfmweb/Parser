import { describe, expect, it } from 'vitest';
import { canonicalizeYandexMapsUrl, extractYandexOrgId, isDuplicateYandexOrganization, isYandexMapsUrl } from '@/utils/yandexUrl';

describe('canonicalizeYandexMapsUrl', () => {
  it('strips query parameters', () => {
    expect(
      canonicalizeYandexMapsUrl(
        'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/?ll=55.989556%2C54.737533&z=17.69',
      ),
    ).toBe('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/');
  });

  it('strips hash fragments', () => {
    expect(
      canonicalizeYandexMapsUrl('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/#inside'),
    ).toBe('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/');
  });

  it('keeps a clean borshch url unchanged', () => {
    const url = 'https://yandex.ru/maps/org/the_borshch/138203157812/';

    expect(canonicalizeYandexMapsUrl(url)).toBe(url);
  });
});

describe('isYandexMapsUrl', () => {
  it('accepts yandex maps links', () => {
    expect(isYandexMapsUrl('https://yandex.ru/maps/org/cafe/1/')).toBe(true);
  });
});

describe('extractYandexOrgId', () => {
  it('extracts id from a slug url', () => {
    expect(extractYandexOrgId('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/')).toBe(
      '1123212619',
    );
  });

  it('extracts id when slug is omitted', () => {
    expect(extractYandexOrgId('https://yandex.ru/maps/org/1123212619/')).toBe('1123212619');
  });

  it('returns null when org id is missing', () => {
    expect(extractYandexOrgId('https://yandex.ru/maps/moscow')).toBeNull();
  });

  it('extracts id from a yandex.com reviews url', () => {
    expect(extractYandexOrgId('https://yandex.com/maps/org/name/12345678/reviews/')).toBe('12345678');
  });

  it('returns null for 2gis and google urls', () => {
    expect(extractYandexOrgId('https://2gis.ru/moscow/firm/123')).toBeNull();
    expect(extractYandexOrgId('https://google.com/maps')).toBeNull();
  });
});

describe('isDuplicateYandexOrganization', () => {
  const existing = [
    {
      yandex_id: '1123212619',
      yandex_url: 'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/',
    },
  ];

  it('detects the same org without a slug', () => {
    expect(isDuplicateYandexOrganization('https://yandex.ru/maps/org/1123212619/', existing)).toBe(
      true,
    );
  });

  it('allows a different org id', () => {
    expect(
      isDuplicateYandexOrganization('https://yandex.ru/maps/org/the_borshch/138203157812/', existing),
    ).toBe(false);
  });
});
