<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\ReviewDTO;
use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    /**
     * @param  list<ReviewDTO>  $reviews
     */
    public function upsertBatch(int $organizationId, array $reviews): int
    {
        if ($reviews === []) {
            return 0;
        }

        $now = now();
        $rows = [];

        foreach ($reviews as $review) {
            $rows[] = [
                'organization_id' => $organizationId,
                'yandex_review_id' => $review->yandexReviewId,
                'author_name' => $review->authorName,
                'author_url' => $review->authorUrl,
                'rating' => $review->rating,
                'text' => $review->text,
                'reviewed_at' => $review->reviewedAt->format('Y-m-d H:i:s'),
                'first_seen_at' => $now,
                'updated_at' => $now,
            ];
        }

        return Review::query()->upsert(
            $rows,
            ['organization_id', 'yandex_review_id'],
            ['author_name', 'author_url', 'rating', 'text', 'reviewed_at', 'updated_at'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateByOrganization(int $organizationId, int $page, int $perPage = 50): LengthAwarePaginator
    {
        return Review::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('reviewed_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
