<?php

declare(strict_types=1);

namespace App\Services\Parser\Exceptions;

use Illuminate\Support\Facades\Log;
use Throwable;

class SourceChangedException extends ParserException
{
    public function __construct(
        string $fieldName,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $logMessage = "Structure changed: missing field {$fieldName}";

        Log::error($logMessage, [
            'field' => $fieldName,
        ]);

        parent::__construct('Не удалось разобрать страницу Яндекса. Попробуйте позже.', $code, $previous);
    }
}
