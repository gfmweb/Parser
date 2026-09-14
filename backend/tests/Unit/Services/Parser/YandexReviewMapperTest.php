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

it('maps author publicId to maps user url when uri is missing', function () use ($mapper) {
    $reviews = $mapper->map([
        'reviews' => [[
            'reviewId' => 'r1',
            'rating' => 5,
            'updatedTime' => '2024-01-15T10:30:00Z',
            'author' => ['name' => 'Имя', 'publicId' => 'abc'],
        ]],
    ]);

    expect($reviews[0]->authorUrl)->toBe('https://yandex.ru/maps/user/abc');
});

it('maps legacy data.reviews payload with id and createdTime', function () use ($mapper) {
    $reviews = $mapper->map([
        'data' => [
            'reviews' => [[
                'id' => 'legacy',
                'rating' => 3,
                'createdTime' => '2024-03-01T00:00:00Z',
                'author' => ['name' => 'Старый'],
            ]],
        ],
    ]);

    expect($reviews)->toHaveCount(1)
        ->and($reviews[0]->yandexReviewId)->toBe('legacy')
        ->and($reviews[0]->rating)->toBe(3);
});

it('throws SourceChangedException and logs when required fields are missing', function () use ($mapper) {
    Log::spy();

    $payload = [
        'reviews' => [
            [
                'author' => ['name' => 'Имя'],
                'text' => 'без id',
            ],
        ],
    ];

    expect(fn () => $mapper->map($payload))
        ->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].reviewId');

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($encoded): bool {
            return $message === 'Structure changed: missing field reviews[].reviewId'
                && ($context['field'] ?? null) === 'reviews[].reviewId'
                && ($context['json'] ?? null) === $encoded;
        });
});

it('throws SourceChangedException when reviews id is missing', function () use ($mapper) {
    expect(fn () => $mapper->map([
        'reviews' => [[
            'rating' => 5,
            'updatedTime' => '2024-01-15T10:30:00Z',
        ]],
    ]))->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].reviewId');
});

it('throws SourceChangedException when reviews rating is missing', function () use ($mapper) {
    expect(fn () => $mapper->map([
        'reviews' => [[
            'reviewId' => 'review_id',
            'updatedTime' => '2024-01-15T10:30:00Z',
        ]],
    ]))->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].rating');
});

it('throws SourceChangedException when reviews updatedTime is missing', function () use ($mapper) {
    expect(fn () => $mapper->map([
        'reviews' => [[
            'reviewId' => 'review_id',
            'rating' => 5,
        ]],
    ]))->toThrow(SourceChangedException::class, 'Structure changed: missing field reviews[].updatedTime');
});

it('skips reviews with rating outside 1-5', function () use ($mapper) {
    $reviews = $mapper->map([
        'reviews' => [
            [
                'reviewId' => 'zero',
                'rating' => 0,
                'updatedTime' => '2024-01-15T10:30:00Z',
                'author' => ['name' => 'Без оценки'],
            ],
            [
                'reviewId' => 'ok',
                'rating' => 5,
                'updatedTime' => '2024-01-16T10:30:00Z',
                'author' => ['name' => 'Ок'],
            ],
        ],
    ]);

    expect($reviews)->toHaveCount(1)
        ->and($reviews[0]->yandexReviewId)->toBe('ok');
});

it('handles empty reviews array without error', function () use ($mapper) {
    expect($mapper->map(['reviews' => []]))->toBe([]);
});

it('logs only the first 1000 characters of the raw json sample', function () {
    Log::spy();

    $payload = [
        'blob' => str_repeat('я', 4000),
    ];

    $exception = new SourceChangedException('reviews[].reviewId', $payload);

    expect($exception->getMessage())->toBe('Structure changed: missing field reviews[].reviewId');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            $json = $context['json'] ?? null;

            return $message === 'Structure changed: missing field reviews[].reviewId'
                && is_string($json)
                && mb_strlen($json) === 1000;
        });
});
