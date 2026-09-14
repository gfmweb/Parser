<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not create duplicates for the same yandex_review_id', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/upsert-dup',
    ]);

    $repository = app(ReviewRepositoryInterface::class);
    $review = makeReviewDto('rev-1', 4);

    $repository->upsertBatch($organization->id, [$review]);
    $repository->upsertBatch($organization->id, [$review]);

    expect(Review::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

it('updates rating on conflict', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/upsert-rating',
    ]);

    $repository = app(ReviewRepositoryInterface::class);

    $repository->upsertBatch($organization->id, [makeReviewDto('rev-1', 3)]);
    $repository->upsertBatch($organization->id, [makeReviewDto('rev-1', 5)]);

    $stored = Review::query()
        ->where('organization_id', $organization->id)
        ->where('yandex_review_id', 'rev-1')
        ->first();

    expect($stored)->not->toBeNull()
        ->and($stored?->rating)->toBe(5)
        ->and(Review::query()->count())->toBe(1);
});

it('upsertBatch with 3 new reviews creates 3 records', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/upsert-three',
    ]);

    $repository = app(ReviewRepositoryInterface::class);
    $repository->upsertBatch($organization->id, [
        makeReviewDto('rev-a', 5),
        makeReviewDto('rev-b', 4),
        makeReviewDto('rev-c', 3),
    ]);

    expect(Review::query()->where('organization_id', $organization->id)->count())->toBe(3);
});

it('upsertBatch with 1 existing and 1 new updates existing and creates one', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/upsert-mixed',
    ]);

    $repository = app(ReviewRepositoryInterface::class);
    $repository->upsertBatch($organization->id, [makeReviewDto('rev-old', 2, 'old')]);
    $repository->upsertBatch($organization->id, [
        makeReviewDto('rev-old', 5, 'updated'),
        makeReviewDto('rev-new', 4, 'fresh'),
    ]);

    expect(Review::query()->where('organization_id', $organization->id)->count())->toBe(2);

    $updated = Review::query()->where('yandex_review_id', 'rev-old')->first();

    expect($updated?->rating)->toBe(5)
        ->and($updated?->text)->toBe('updated');
});

it('paginateByOrganization returns 50 per page', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/paginate-50',
    ]);
    seedOrganizationReviews($organization, 55);

    $page = app(ReviewRepositoryInterface::class)->paginateByOrganization($organization->id, 1);

    expect($page->perPage())->toBe(50)
        ->and($page->count())->toBe(50)
        ->and($page->total())->toBe(55);
});

it('paginateByOrganization page 2 skips first 50', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/paginate-page-2',
    ]);
    seedOrganizationReviews($organization, 55);

    $page = app(ReviewRepositoryInterface::class)->paginateByOrganization($organization->id, 2);

    expect($page->currentPage())->toBe(2)
        ->and($page->count())->toBe(5)
        ->and($page->first()?->yandex_review_id)->toBe('rev-51');
});
