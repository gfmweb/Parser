<?php

declare(strict_types=1);

namespace App\Services\Parser;

use InvalidArgumentException;

class YandexUrlParser
{
    // Канонизация URL зеркалится во frontend/src/utils/yandexUrl.ts.
    private const MAPS_HOST_PATTERN = '#https?://(?:www\.)?yandex\.(?:ru|com)/maps(?:/|$)#i';

    private const ORG_ID_WITH_SLUG_PATTERN = '#/maps/org/[^/]+/(\d+)(?:/|$)#i';

    private const ORG_ID_ONLY_PATTERN = '#/maps/org/(\d+)(?:/|$)#i';

    private const ORG_SLUG_PATTERN = '#/maps/org/([^/]+)/(\d+)(?:/|$)#i';

    public function canonicalize(string $url): string
    {
        $trimmed = trim($url);
        $parts = parse_url($trimmed);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $withoutFragment = explode('#', $trimmed, 2)[0];

            return explode('?', $withoutFragment, 2)[0];
        }

        $canonical = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $canonical .= ':'.$parts['port'];
        }

        $canonical .= $parts['path'] ?? '';

        return $canonical;
    }

    public function extractOrgId(string $url): string
    {
        if (preg_match(self::MAPS_HOST_PATTERN, $url) !== 1) {
            throw new InvalidArgumentException('Invalid Yandex Maps URL');
        }

        if (preg_match(self::ORG_ID_WITH_SLUG_PATTERN, $url, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match(self::ORG_ID_ONLY_PATTERN, $url, $matches) === 1) {
            return $matches[1];
        }

        throw new InvalidArgumentException('Invalid Yandex Maps URL');
    }

    public function extractSlug(string $url): ?string
    {
        if (preg_match(self::ORG_SLUG_PATTERN, $url, $matches) !== 1) {
            return null;
        }

        if (ctype_digit($matches[1])) {
            return null;
        }

        return $matches[1];
    }
}
