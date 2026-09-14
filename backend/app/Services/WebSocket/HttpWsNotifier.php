<?php

declare(strict_types=1);

namespace App\Services\WebSocket;

use App\DTOs\ParseProgressDTO;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HttpWsNotifier implements WsNotifierInterface
{
    public function __construct(private readonly Client $http) {}

    public function sendProgress(ParseProgressDTO $progress): void
    {
        $url = rtrim((string) config('services.ws_server.url'), '/').'/internal/progress';

        try {
            $this->http->post($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Internal-Secret' => (string) config('services.ws_server.internal_secret'),
                ],
                'json' => $progress->toArray(),
                'timeout' => 5,
                'http_errors' => false,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to notify ws-server', [
                'message' => $exception->getMessage(),
                'parse_job_id' => $progress->parseJobId,
            ]);
        }
    }
}
