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
        ->toThrow(SourceChangedException::class, 'Structure changed: missing field stack');
});
