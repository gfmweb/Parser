<?php

declare(strict_types=1);

use App\Services\Parser\Exceptions\SourceChangedException;
use App\Services\Parser\YandexMapsStateExtractor;

$extractor = new YandexMapsStateExtractor;

it('extracts stack json from an html script tag', function () use ($extractor) {
    $html = (string) file_get_contents(base_path('tests/Fixtures/yandex_org_page.html'));
    $state = $extractor->extract($html);

    expect($state['stack'][0]['results']['items'][0]['title'] ?? null)->toBe('Cafe');
});

it('throws SourceChangedException when stack json is missing', function () use ($extractor) {
    expect(fn () => $extractor->extract('<html><script>var x = 1;</script></html>'))
        ->toThrow(SourceChangedException::class, 'Не удалось разобрать страницу Яндекса. Попробуйте позже.');
});

it('prefers the script whose org item has reviewResults', function () use ($extractor) {
    $withoutReviews = '{"stack":[{"results":{"items":[{"title":"Lite","ratingData":{"ratingValue":4,"ratingCount":1,"reviewCount":10}}]}}]}';
    $withReviews = '{"stack":[{"results":{"items":[{"title":"Full","ratingData":{"ratingValue":4,"ratingCount":1,"reviewCount":10},"reviewResults":{"reviews":[]}}]}}]}';
    $html = '<html><script>'.$withoutReviews.'</script><script>'.$withReviews.'</script></html>';

    $state = $extractor->extract($html);

    expect($state['stack'][0]['results']['items'][0]['title'] ?? null)->toBe('Full');
});
