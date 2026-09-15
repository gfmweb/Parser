<?php

declare(strict_types=1);

use App\Services\Parser\Exceptions\OrganizationNotFoundException;
use App\Services\Parser\Exceptions\SourceChangedException;
use App\Services\Parser\Mappers\YandexReviewMapper;
use App\Services\Parser\YandexApiClient;
use App\Services\Parser\YandexMapsParser;
use App\Services\Parser\YandexUrlParser;

/**
 * @return array<string, mixed>
 */
function yandexOrgStateFixture(): array
{
    /** @var array<string, mixed> $state */
    $state = json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/yandex_org_state.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    return $state;
}

it('parses organization meta and reviews from ssr state', function () {
    $state = yandexOrgStateFixture();
    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')
        ->once()
        ->with('138203157812', 0, 50, 'the_borshch')
        ->andReturn($state);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);
    $parsed = $parser->parse('https://yandex.ru/maps/org/the_borshch/138203157812/');

    expect($parsed->yandexId)->toBe('138203157812')
        ->and($parsed->name)->toBe('The Borщ')
        ->and($parsed->address)->toBe('Комсомольская ул., 15, Уфа')
        ->and($parsed->rating)->toBe(4.4)
        ->and($parsed->ratingCount)->toBe(1008)
        ->and($parsed->reviewCount)->toBe(478)
        ->and($parsed->reviews)->toHaveCount(2)
        ->and($parsed->reviews[0]->yandexReviewId)->toBe('review_id')
        ->and($parsed->reviews[0]->authorUrl)->toBe('https://yandex.ru/maps/user/user-1');
});

it('throws OrganizationNotFoundException when stack items are empty', function () {
    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')->once()->andReturn([
        'stack' => [['results' => ['items' => []]]],
    ]);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);

    expect(fn () => $parser->parse('https://yandex.ru/maps/org/missing/1/'))
        ->toThrow(OrganizationNotFoundException::class, 'Организация не найдена в Яндекс Картах.');
});

it('throws SourceChangedException when title is missing', function () {
    $state = yandexOrgStateFixture();
    unset($state['stack'][0]['results']['items'][0]['title']);

    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')->once()->andReturn($state);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);

    expect(fn () => $parser->parse('https://yandex.ru/maps/org/the_borshch/138203157812/'))
        ->toThrow(SourceChangedException::class, 'Не удалось разобрать страницу Яндекса. Попробуйте позже.');
});

it('parses organization with zero reviews when reviewResults is missing', function () {
    $state = [
        'stack' => [[
            'results' => [
                'items' => [[
                    'type' => 'business',
                    'title' => 'Пустая',
                    'address' => 'Уфа',
                    'ratingData' => [
                        'ratingValue' => 0,
                        'ratingCount' => 0,
                        'reviewCount' => 0,
                    ],
                ]],
            ],
        ]],
    ];

    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')->once()->andReturn($state);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);
    $parsed = $parser->parse('https://yandex.ru/maps/org/empty/1/');

    expect($parsed->name)->toBe('Пустая')
        ->and($parsed->reviewCount)->toBe(0)
        ->and($parsed->reviews)->toBe([]);
});

it('prefers the business item over a preceding non-business card', function () {
    $state = yandexOrgStateFixture();
    $business = $state['stack'][0]['results']['items'][0];
    $state['stack'][0]['results']['items'] = [
        ['type' => 'advert', 'title' => 'Реклама'],
        $business,
    ];

    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')->once()->andReturn($state);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);
    $parsed = $parser->parse('https://yandex.ru/maps/org/the_borshch/138203157812/');

    expect($parsed->name)->toBe('The Borщ')
        ->and($parsed->reviews)->toHaveCount(2);
});

it('throws SourceChangedException when the first page has no reviewResults', function () {
    $state = [
        'stack' => [[
            'results' => [
                'items' => [[
                    'type' => 'business',
                    'title' => 'Своя компания',
                    'address' => 'Уфа',
                    'ratingData' => [
                        'ratingValue' => 4.5,
                        'ratingCount' => 10,
                        'reviewCount' => 1023,
                    ],
                ]],
            ],
        ]],
    ];

    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')->once()->andReturn($state);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);

    expect(fn () => $parser->parse('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/'))
        ->toThrow(SourceChangedException::class, 'Не удалось разобрать страницу Яндекса. Попробуйте позже.');
});

it('stops pagination when a later page has no reviewResults', function () {
    $first = yandexOrgStateFixture();
    $template = $first['stack'][0]['results']['items'][0]['reviewResults']['reviews'][0];
    $first['stack'][0]['results']['items'][0]['ratingData']['reviewCount'] = 1023;
    $first['stack'][0]['results']['items'][0]['reviewResults']['params'] = [
        'offset' => 0,
        'limit' => 50,
        'count' => 1023,
        'page' => 1,
        'totalPages' => 21,
    ];
    $first['stack'][0]['results']['items'][0]['reviewResults']['reviews'] = array_map(
        static function (int $index) use ($template): array {
            $review = $template;
            $review['reviewId'] = 'review_'.$index;

            return $review;
        },
        range(1, 50),
    );

    $later = $first;
    unset($later['stack'][0]['results']['items'][0]['reviewResults']);

    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')
        ->once()
        ->with('1123212619', 0, 50, 'svoya_kompaniya')
        ->andReturn($first);
    $client->shouldReceive('getReviews')
        ->once()
        ->with('1123212619', 50, 50, 'svoya_kompaniya')
        ->andReturn($later);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);
    $parsed = $parser->parse('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/');

    expect($parsed->name)->toBe('The Borщ')
        ->and($parsed->reviews)->toHaveCount(50)
        ->and($parsed->reviewCount)->toBe(50)
        ->and($parsed->incomplete)->toBeTrue();
});

it('invokes onMeta after the first page before fetching the next', function () {
    $first = yandexOrgStateFixture();
    $template = $first['stack'][0]['results']['items'][0]['reviewResults']['reviews'][0];
    $first['stack'][0]['results']['items'][0]['reviewResults']['params'] = [
        'offset' => 0,
        'limit' => 50,
        'count' => 478,
        'page' => 1,
        'totalPages' => 2,
    ];
    $first['stack'][0]['results']['items'][0]['reviewResults']['reviews'] = array_map(
        static function (int $index) use ($template): array {
            $review = $template;
            $review['reviewId'] = 'review_'.$index;

            return $review;
        },
        range(1, 50),
    );

    $later = $first;
    $later['stack'][0]['results']['items'][0]['reviewResults']['params']['page'] = 2;
    $later['stack'][0]['results']['items'][0]['reviewResults']['reviews'] = [];

    $secondPageFetched = false;
    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')
        ->once()
        ->with('138203157812', 0, 50, 'the_borshch')
        ->andReturn($first);
    $client->shouldReceive('getReviews')
        ->once()
        ->with('138203157812', 50, 50, 'the_borshch')
        ->andReturnUsing(function () use (&$secondPageFetched, $later): array {
            $secondPageFetched = true;

            return $later;
        });

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);
    $metaName = null;
    $parser->parse(
        'https://yandex.ru/maps/org/the_borshch/138203157812/',
        null,
        function ($meta) use (&$metaName, &$secondPageFetched): void {
            expect($secondPageFetched)->toBeFalse();
            $metaName = $meta->name;
        },
    );

    expect($metaName)->toBe('The Borщ')
        ->and($secondPageFetched)->toBeTrue();
});

it('stops after 600 reviews and does not fetch the next page', function () {
    $first = yandexOrgStateFixture();
    $template = $first['stack'][0]['results']['items'][0]['reviewResults']['reviews'][0];
    $first['stack'][0]['results']['items'][0]['ratingData']['reviewCount'] = 1023;
    $first['stack'][0]['results']['items'][0]['reviewResults']['params'] = [
        'offset' => 0,
        'limit' => 50,
        'count' => 1023,
        'page' => 1,
        'totalPages' => 21,
    ];
    $first['stack'][0]['results']['items'][0]['reviewResults']['reviews'] = array_map(
        static function (int $index) use ($template): array {
            $review = $template;
            $review['reviewId'] = 'review_'.$index;

            return $review;
        },
        range(1, 600),
    );

    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')
        ->once()
        ->with('1123212619', 0, 50, 'svoya_kompaniya')
        ->andReturn($first);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);
    $parsed = $parser->parse('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/');

    expect($parsed->reviews)->toHaveCount(600)
        ->and($parsed->reviewCount)->toBe(600)
        ->and($parsed->incomplete)->toBeFalse();
});
