<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class OrganizationAlreadyParsingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Организация уже парсится.');
    }
}
