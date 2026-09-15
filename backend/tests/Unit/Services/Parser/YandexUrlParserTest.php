<?php

declare(strict_types=1);

use App\Services\Parser\YandexUrlParser;

$parser = new YandexUrlParser;

it('extracts org id from yandex.ru maps org url', function () use ($parser) {
    expect($parser->extractOrgId('https://yandex.ru/maps/org/кафе/12345678/'))->toBe('12345678');
});

it('extracts org id from yandex.com reviews url', function () use ($parser) {
    expect($parser->extractOrgId('https://yandex.com/maps/org/name/12345678/reviews/'))->toBe('12345678');
});

it('extracts org id when slug is omitted', function () use ($parser) {
    expect($parser->extractOrgId('https://www.yandex.ru/maps/org/999888777/'))->toBe('999888777');
});

it('throws on 2gis url', function () use ($parser) {
    $parser->extractOrgId('https://2gis.ru/moscow/firm/123');
})->throws(InvalidArgumentException::class, 'Invalid Yandex Maps URL');

it('throws on google maps url', function () use ($parser) {
    $parser->extractOrgId('https://google.com/maps');
})->throws(InvalidArgumentException::class, 'Invalid Yandex Maps URL');

it('throws on empty string', function () use ($parser) {
    $parser->extractOrgId('');
})->throws(InvalidArgumentException::class, 'Invalid Yandex Maps URL');

it('throws when org id is missing', function () use ($parser) {
    $parser->extractOrgId('https://yandex.ru/maps/moscow');
})->throws(InvalidArgumentException::class, 'Invalid Yandex Maps URL');

it('canonicalizes yandex url by stripping query string', function () use ($parser) {
    expect($parser->canonicalize(
        'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/?ll=55.989556%2C54.737533&z=17.69',
    ))->toBe('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/');
});

it('canonicalizes yandex url by stripping fragment', function () use ($parser) {
    expect($parser->canonicalize(
        'https://yandex.ru/maps/org/svoya_kompaniya/1123212619/#inside',
    ))->toBe('https://yandex.ru/maps/org/svoya_kompaniya/1123212619/');
});

it('keeps borshch url path when there are no query params', function () use ($parser) {
    $url = 'https://yandex.ru/maps/org/the_borshch/138203157812/';

    expect($parser->canonicalize($url))->toBe($url);
});

it('extracts slug from org url', function () use ($parser) {
    expect($parser->extractSlug('https://yandex.ru/maps/org/the_borshch/138203157812/'))
        ->toBe('the_borshch');
});

it('returns null slug when org id is the path segment', function () use ($parser) {
    expect($parser->extractSlug('https://yandex.ru/maps/org/138203157812/'))->toBeNull();
});
