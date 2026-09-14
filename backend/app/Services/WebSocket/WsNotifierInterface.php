<?php

declare(strict_types=1);

namespace App\Services\WebSocket;

use App\DTOs\ParseProgressDTO;

interface WsNotifierInterface
{
    public function sendProgress(ParseProgressDTO $progress): void;
}
