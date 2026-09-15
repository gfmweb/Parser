<?php

declare(strict_types=1);

namespace App\Services\Parser;

use App\Services\Parser\Exceptions\SourceChangedException;
use JsonException;

final class YandexMapsStateExtractor
{
    private const JSON_DEPTH = 4096;

    /**
     * Достаёт встроенный JSON-стейт карточки из HTML Яндекс Карт.
     *
     * @return array<string, mixed>
     */
    public function extract(string $html): array
    {
        if (preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $matches) === false) {
            throw new SourceChangedException('stack');
        }

        $fallback = null;

        foreach ($matches[1] as $script) {
            $trimmed = trim($script);

            if ($trimmed === '' || ! str_starts_with($trimmed, '{') || ! str_contains($trimmed, '"stack"')) {
                continue;
            }

            try {
                $decoded = json_decode($trimmed, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (! is_array($decoded) || ! isset($decoded['stack'])) {
                continue;
            }

            /** @var array<string, mixed> $decoded */
            if (self::hasReviewResults($decoded)) {
                return $decoded;
            }

            $fallback ??= $decoded;
        }

        if (is_array($fallback)) {
            return $fallback;
        }

        throw new SourceChangedException('stack');
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null
     */
    public static function businessItem(array $state): ?array
    {
        $stack = $state['stack'] ?? null;

        if (! is_array($stack)) {
            return null;
        }

        $candidates = [];

        foreach ($stack as $frame) {
            if (! is_array($frame)) {
                continue;
            }

            $items = $frame['results']['items'] ?? null;

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $candidates[] = $item;
                }
            }
        }

        foreach ($candidates as $item) {
            if (($item['type'] ?? null) === 'business') {
                /** @var array<string, mixed> $item */
                return $item;
            }
        }

        foreach ($candidates as $item) {
            if (is_string($item['title'] ?? null) && is_array($item['ratingData'] ?? null)) {
                /** @var array<string, mixed> $item */
                return $item;
            }
        }

        $first = $candidates[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function hasReviewResults(array $state): bool
    {
        $item = self::businessItem($state);

        return is_array($item) && isset($item['reviewResults']) && is_array($item['reviewResults']);
    }
}
