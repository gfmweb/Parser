<?php

declare(strict_types=1);

use App\Services\Parser\Exceptions\ParserException;
use App\Services\Parser\YandexApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

it('throws Rate limited when Yandex responds with HTTP 429', function () {
    $mock = new MockHandler([new Response(429)]);
    $client = new Client([
        'handler' => HandlerStack::create($mock),
        'http_errors' => false,
    ]);

    $api = new YandexApiClient($client);

    expect(fn () => $api->getOrgInfo('12345678'))
        ->toThrow(ParserException::class, 'Rate limited');
});
