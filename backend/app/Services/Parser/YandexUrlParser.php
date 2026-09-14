<?php

declare(strict_types=1);

namespace App\Services\Parser;

use InvalidArgumentException;

class YandexUrlParser
{
    private const MAPS_HOST_PATTERN = '#https?://(?:www\.)?yandex\.(?:ru|com)/maps(?:/|$)#i';

    private const ORG_ID_WITH_SLUG_PATTERN = '#/maps/org/[^/]+/(\d+)(?:/|$)#i';

    private const ORG_ID_ONLY_PATTERN = '#/maps/org/(\d+)(?:/|$)#i';

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
}
