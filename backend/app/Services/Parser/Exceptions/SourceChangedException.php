<?php

declare(strict_types=1);

namespace App\Services\Parser\Exceptions;

use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class SourceChangedException extends ParserException
{
    public function __construct(
        string $fieldName,
        mixed $jsonSample = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $logMessage = "Structure changed: missing field {$fieldName}";

        Log::error($logMessage, [
            'field' => $fieldName,
            'json' => self::previewJson($jsonSample),
        ]);

        parent::__construct('Не удалось разобрать страницу Яндекса. Попробуйте позже.', $code, $previous);
    }

    private static function previewJson(mixed $jsonSample): string
    {
        try {
            $encoded = json_encode($jsonSample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        if (mb_strlen($encoded) <= 1000) {
            return $encoded;
        }

        return mb_substr($encoded, 0, 1000);
    }
}
