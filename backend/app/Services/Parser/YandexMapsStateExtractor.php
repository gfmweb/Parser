<?php

declare(strict_types=1);

namespace App\Services\Parser;

use App\Services\Parser\Exceptions\SourceChangedException;
use JsonException;

final class YandexMapsStateExtractor
{
    /**
     * Достаёт встроенный JSON-стейт карточки из HTML Яндекс Карт.
     *
     * @return array<string, mixed>
     */
    public function extract(string $html): array
    {
        if (preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $matches) === false) {
            throw new SourceChangedException('stack', ['html' => $html]);
        }

        foreach ($matches[1] as $script) {
            $trimmed = trim($script);

            if ($trimmed === '' || ! str_starts_with($trimmed, '{') || ! str_contains($trimmed, '"stack"')) {
                continue;
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (is_array($decoded) && isset($decoded['stack'])) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        throw new SourceChangedException('stack', ['html' => $html]);
    }
}
