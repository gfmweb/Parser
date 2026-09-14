<?php

declare(strict_types=1);

use App\DTOs\OrganizationDataDTO;
use App\DTOs\ReviewDTO;
use App\Enums\ParseJobStatus;
use App\Enums\ParseStatus;
use App\Models\Organization;
use App\Models\ParseJob;
use App\Models\Review;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit/Repositories');

pest()->extend(TestCase::class)
    ->in('Unit/Services');

pest()->extend(TestCase::class)
    ->in('Unit/Jobs');

function makeReviewDto(
    string $yandexReviewId = 'rev-1',
    int $rating = 5,
    ?string $text = 'Review text',
    string $reviewedAt = '2024-01-15 12:00:00',
): ReviewDTO {
    return new ReviewDTO(
        yandexReviewId: $yandexReviewId,
        authorName: 'Author',
        authorUrl: null,
        rating: $rating,
        text: $text,
        reviewedAt: new DateTimeImmutable($reviewedAt),
    );
}

function parsedOrganizationData(float $rating = 4.8, int $ratingCount = 12, int $reviewCount = 1): OrganizationDataDTO
{
    return new OrganizationDataDTO(
        yandexId: '12345678',
        name: 'Cafe Test',
        address: 'Moscow',
        rating: $rating,
        ratingCount: $ratingCount,
        reviewCount: $reviewCount,
        reviews: [
            makeReviewDto('rev-1', 5, 'Great', '2024-01-15 12:00:00'),
        ],
    );
}

/**
 * @return array{0: Organization, 1: ParseJob}
 */
function seedParseJob(): array
{
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'user_id' => $user->id,
        'yandex_url' => 'https://yandex.ru/maps/org/cafe/12345678/',
        'parse_status' => ParseStatus::Pending,
    ]);
    $parseJob = ParseJob::query()->create([
        'organization_id' => $organization->id,
        'status' => ParseJobStatus::Queued,
    ]);

    return [$organization, $parseJob];
}

function seedOrganizationReviews(Organization $organization, int $count): void
{
    $now = now();
    $rows = [];

    for ($i = 1; $i <= $count; $i++) {
        $rows[] = [
            'organization_id' => $organization->id,
            'yandex_review_id' => 'rev-'.$i,
            'author_name' => 'Author '.$i,
            'author_url' => null,
            'rating' => 5,
            'text' => 'Review '.$i,
            'reviewed_at' => $now->copy()->subMinutes($i),
            'first_seen_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($rows, 50) as $chunk) {
        Review::query()->insert($chunk);
    }
}
