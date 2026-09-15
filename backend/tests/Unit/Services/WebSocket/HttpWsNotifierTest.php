<?php

declare(strict_types=1);

use App\DTOs\ParseProgressDTO;
use App\Services\WebSocket\HttpWsNotifier;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('posts parse progress json to the ws-server internal endpoint', function () {
    config([
        'services.ws_server.url' => 'http://ws-server:6001',
        'services.ws_server.internal_secret' => 'test-secret',
    ]);

    $history = [];
    $mock = new MockHandler([new Response(204)]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $notifier = new HttpWsNotifier(new Client(['handler' => $stack]));

    $progress = new ParseProgressDTO(
        organizationId: 7,
        parseJobId: 11,
        total: 20,
        parsed: 10,
        status: 'parsing',
        error: null,
    );

    $notifier->sendProgress($progress);

    expect($history)->toHaveCount(1);

    /** @var Request $request */
    $request = $history[0]['request'];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('http://ws-server:6001/internal/progress')
        ->and($request->getHeaderLine('X-Internal-Secret'))->toBe('test-secret')
        ->and(json_decode((string) $request->getBody(), true))->toBe([
            'organizationId' => 7,
            'parseJobId' => 11,
            'total' => 20,
            'parsed' => 10,
            'status' => 'parsing',
            'error' => null,
            'name' => null,
            'rating' => null,
            'address' => null,
        ]);
});

it('swallows http errors without throwing', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new Request('POST', 'http://ws-server:6001/internal/progress')),
    ]);
    $notifier = new HttpWsNotifier(new Client(['handler' => HandlerStack::create($mock)]));

    $progress = new ParseProgressDTO(
        organizationId: 1,
        parseJobId: 2,
        total: 0,
        parsed: 0,
        status: 'failed',
        error: 'boom',
    );

    expect(fn () => $notifier->sendProgress($progress))->not->toThrow(Throwable::class);
});
