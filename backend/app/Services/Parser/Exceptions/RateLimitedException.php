<?php

declare(strict_types=1);

namespace App\Services\Parser\Exceptions;

use Throwable;

class RateLimitedException extends ParserException
{
    public function __construct(int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct('Слишком много запросов к Яндексу. Попробуйте позже.', $code, $previous);
    }
}
