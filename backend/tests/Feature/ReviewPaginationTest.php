<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('returns 50 reviews per page', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/cafe/12345678/',
    ]);
    seedOrganizationReviews($organization, 55);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews?page=1');

    $response->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 55)
        ->assertJsonPath('meta.last_page', 2);

    expect($response->json('data'))->toHaveCount(50);
});

it('returns empty array on page beyond last', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/empty-page/12345678/',
    ]);
    seedOrganizationReviews($organization, 3);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews?page=99');

    $response->assertOk()
        ->assertJsonPath('meta.current_page', 99);

    expect($response->json('data'))->toBe([]);
});

it('reviews are ordered by reviewed_at DESC', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/ordered/12345678/',
    ]);
    seedOrganizationReviews($organization, 5);

    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews');

    $dates = array_map(
        static fn (mixed $value): string => is_string($value) ? $value : '',
        $response->json('data.*.reviewed_at') ?? [],
    );

    $sorted = $dates;
    rsort($sorted);

    expect($dates)->toBe($sorted)
        ->and($response->json('data.0.author_name'))->toBe('Author 1');
});

it('filters reviews by rating and keeps full-org rating_counts', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/rating-filter/12345678/',
    ]);

    $now = now();
    Review::query()->insert([
        reviewRow($organization->id, 'rev-5a', 5, $now->copy()->subMinutes(1)),
        reviewRow($organization->id, 'rev-5b', 5, $now->copy()->subMinutes(2)),
        reviewRow($organization->id, 'rev-4a', 4, $now->copy()->subMinutes(3)),
        reviewRow($organization->id, 'rev-3a', 3, $now->copy()->subMinutes(4)),
        reviewRow($organization->id, 'rev-1a', 1, $now->copy()->subMinutes(5)),
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $filtered = $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews?rating=5');

    $filtered->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.rating_counts.1', 1)
        ->assertJsonPath('meta.rating_counts.2', 0)
        ->assertJsonPath('meta.rating_counts.3', 1)
        ->assertJsonPath('meta.rating_counts.4', 1)
        ->assertJsonPath('meta.rating_counts.5', 2);

    $ratings = $filtered->json('data.*.rating');
    expect($ratings)->toHaveCount(2)
        ->and($ratings)->each->toBe(5);

    $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews')
        ->assertOk()
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.rating_counts.5', 2);
});

it('paginates within a rating filter', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/rating-pages/12345678/',
    ]);
    seedOrganizationReviews($organization, 55);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews?rating=5&page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 55)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonCount(5, 'data');
});

it('rejects invalid rating filter values', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/rating-invalid/12345678/',
    ]);

    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews?rating=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating']);

    $this->withToken($token)
        ->getJson('/api/organizations/'.$organization->id.'/reviews?rating=6')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating']);
});

/**
 * @return array<string, mixed>
 */
function reviewRow(int $organizationId, string $yandexReviewId, int $rating, Carbon $reviewedAt): array
{
    $now = now();

    return [
        'organization_id' => $organizationId,
        'yandex_review_id' => $yandexReviewId,
        'author_name' => 'Author',
        'author_url' => null,
        'rating' => $rating,
        'text' => 'Review',
        'reviewed_at' => $reviewedAt,
        'first_seen_at' => $now,
        'updated_at' => $now,
    ];
}
