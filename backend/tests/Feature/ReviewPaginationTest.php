<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
