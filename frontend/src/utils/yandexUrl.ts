const YANDEX_MAPS_URL_PATTERN = /yandex\.(ru|com)\/maps/i;

export function isYandexMapsUrl(url: string): boolean {
  return YANDEX_MAPS_URL_PATTERN.test(url.trim());
}
