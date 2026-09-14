<?php

declare(strict_types=1);

namespace App\Events;

use App\DTOs\ParseProgressDTO;

readonly class ParseProgressEvent
{
    public function __construct(public ParseProgressDTO $progress) {}
}
