<?php

declare(strict_types=1);

use App\Services\Parser\Exceptions\RateLimitedException;
use App\Services\Parser\YandexApiClient;
use App\Services\Parser\YandexMapsStateExtractor;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

it('does not retry HTTP 429 in Guzzle middleware', function () {
    $mock = new MockHandler([
        new Response(429),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(YandexApiClient::retryMiddleware());
    $client = new Client([
        'handler' => $stack,
        'http_errors' => false,
    ]);

    $api = new YandexApiClient($client);

    expect(fn () => $api->getOrgInfo('12345678'))
        ->toThrow(RateLimitedException::class);

    expect($mock->count())->toBe(0);
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

/**
 * @param  array<string, mixed>  $state
 */
function yandexHtmlFromState(array $state): string
{
    return '<html><script>'.json_encode($state, JSON_THROW_ON_ERROR).'</script></html>';
}

it('requests reviews page with slug when provided', function () {
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
    $api->getReviews('1123212619', 0, 50, 'svoya_kompaniya');

    expect((string) $history[0]['request']->getUri())
        ->toContain('/maps/org/svoya_kompaniya/1123212619/reviews/');
});

it('retries html fetch when first ssr payload has no reviewResults', function () {
    $incomplete = yandexHtmlFromState([
        'stack' => [[
            'results' => [
                'items' => [[
                    'type' => 'business',
                    'title' => 'Своя компания',
                    'ratingData' => [
                        'ratingValue' => 4.5,
                        'ratingCount' => 10,
                        'reviewCount' => 100,
                    ],
                ]],
            ],
        ]],
    ]);
    $complete = yandexHtmlFromState([
        'stack' => [[
            'results' => [
                'items' => [[
                    'type' => 'business',
                    'title' => 'Своя компания',
                    'ratingData' => [
                        'ratingValue' => 4.5,
                        'ratingCount' => 10,
                        'reviewCount' => 100,
                    ],
                    'reviewResults' => [
                        'reviews' => [],
                        'params' => ['page' => 1, 'totalPages' => 1],
                    ],
                ]],
            ],
        ]],
    ]);

    $history = [];
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'text/html'], $incomplete),
        new Response(200, ['Content-Type' => 'text/html'], $complete),
    ]);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));
    $client = new Client([
        'handler' => $stack,
        'http_errors' => false,
    ]);

    $api = new YandexApiClient($client);
    $state = $api->getReviews('1123212619', 0, 50);

    expect($history)->toHaveCount(2)
        ->and(YandexMapsStateExtractor::hasReviewResults($state))->toBeTrue();
});
