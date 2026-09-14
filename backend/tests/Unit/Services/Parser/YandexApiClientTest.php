<?php

declare(strict_types=1);

use App\Services\Parser\Exceptions\ParserException;
use App\Services\Parser\YandexApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

it('throws Rate limited when Yandex responds with HTTP 429', function () {
    $mock = new MockHandler([
        new Response(429),
        new Response(429),
        new Response(429),
    ]);
    $client = new Client([
        'handler' => HandlerStack::create($mock),
        'http_errors' => false,
    ]);

    $api = new YandexApiClient($client);

    expect(fn () => $api->getOrgInfo('12345678'))
        ->toThrow(ParserException::class, 'Rate limited');
});

it('loads ssr state from the reviews html page', function () {
    $html = (string) file_get_contents(base_path('tests/Fixtures/yandex_org_page.html'));
    $history = [];
    $mock = new MockHandler([new Response(200, ['Content-Type' => 'text/html'], $html)]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $client = new Client([
        'handler' => $stack,
        'http_errors' => false,
    ]);

    $api = new YandexApiClient($client);
    $state = $api->getReviews('12345678', 50, 50);

    expect($state['stack'][0]['results']['items'][0]['title'] ?? null)->toBe('Cafe')
        ->and($history)->toHaveCount(1);

    $request = $history[0]['request'];
    expect((string) $request->getUri())->toContain('/maps/org/12345678/reviews/')
        ->and((string) $request->getUri())->toContain('page=2');
});
