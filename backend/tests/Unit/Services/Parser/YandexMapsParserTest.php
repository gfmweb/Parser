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
        ->with('138203157812', 0, 50)
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
        ->toThrow(OrganizationNotFoundException::class, 'Organization not found.');
});

it('throws SourceChangedException when title is missing', function () {
    $state = yandexOrgStateFixture();
    unset($state['stack'][0]['results']['items'][0]['title']);

    $client = Mockery::mock(YandexApiClient::class);
    $client->shouldReceive('getReviews')->once()->andReturn($state);

    $parser = new YandexMapsParser($client, new YandexUrlParser, new YandexReviewMapper);

    expect(fn () => $parser->parse('https://yandex.ru/maps/org/the_borshch/138203157812/'))
        ->toThrow(SourceChangedException::class, 'Structure changed: missing field stack.0.results.items.0.title');
});
