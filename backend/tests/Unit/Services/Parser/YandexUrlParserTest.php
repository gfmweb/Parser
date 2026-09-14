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
