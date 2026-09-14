<?php

declare(strict_types=1);

use App\DTOs\ReviewDTO;
use App\Services\Parser\Exceptions\SourceChangedException;
use App\Services\Parser\Mappers\YandexReviewMapper;
use Illuminate\Support\Facades\Log;

$mapper = new YandexReviewMapper;

it('maps valid yandex reviews json to ReviewDTO list', function () use ($mapper) {
    $payload = json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/yandex_reviews_response.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $reviews = $mapper->map($payload);

    expect($reviews)->toHaveCount(2)
        ->and($reviews[0])->toBeInstanceOf(ReviewDTO::class)
        ->and($reviews[0]->yandexReviewId)->toBe('review_id')
        ->and($reviews[0]->authorName)->toBe('Имя')
        ->and($reviews[0]->authorUrl)->toBe('https://yandex.ru/user/1')
        ->and($reviews[0]->rating)->toBe(5)
        ->and($reviews[0]->text)->toBe('Отличное место')
        ->and($reviews[1]->yandexReviewId)->toBe('review_id_2')
        ->and($reviews[1]->rating)->toBe(4)
        ->and($reviews[1]->text)->toBeNull();
});

it('throws SourceChangedException and logs when required fields are missing', function () use ($mapper) {
    Log::spy();

    $payload = [
        'data' => [
            'reviews' => [
                [
                    'author' => ['name' => 'Имя'],
                    'text' => 'без id',
                ],
            ],
        ],
    ];

    expect(fn () => $mapper->map($payload))
        ->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].id');

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($encoded): bool {
            return $message === 'Structure changed: missing field reviews[].id'
                && ($context['field'] ?? null) === 'reviews[].id'
                && ($context['json'] ?? null) === $encoded;
        });
});

it('throws SourceChangedException when reviews id is missing', function () use ($mapper) {
    expect(fn () => $mapper->map([
        'data' => [
            'reviews' => [[
                'rating' => 5,
                'createdTime' => '2024-01-15T10:30:00Z',
            ]],
        ],
    ]))->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].id');
});

it('throws SourceChangedException when reviews rating is missing', function () use ($mapper) {
    expect(fn () => $mapper->map([
        'data' => [
            'reviews' => [[
                'id' => 'review_id',
                'createdTime' => '2024-01-15T10:30:00Z',
            ]],
        ],
    ]))->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].rating');
});

it('throws SourceChangedException when reviews createdTime is missing', function () use ($mapper) {
    expect(fn () => $mapper->map([
        'data' => [
            'reviews' => [[
                'id' => 'review_id',
                'rating' => 5,
            ]],
        ],
    ]))->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].createdTime');
});

it('handles empty reviews array without error', function () use ($mapper) {
    expect($mapper->map(['data' => ['reviews' => []]]))->toBe([]);
});

it('logs only the first 1000 characters of the raw json sample', function () {
    Log::spy();

    $payload = [
        'blob' => str_repeat('я', 4000),
    ];

    $exception = new SourceChangedException('reviews[].id', $payload);

    expect($exception->getMessage())->toBe('Structure changed: missing field reviews[].id');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            $json = $context['json'] ?? null;

            return $message === 'Structure changed: missing field reviews[].id'
                && is_string($json)
                && mb_strlen($json) === 1000;
        });
});
