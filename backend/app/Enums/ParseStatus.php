<?php

declare(strict_types=1);

namespace App\Enums;

enum ParseStatus: string
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case Done = 'done';
    case Failed = 'failed';
}
