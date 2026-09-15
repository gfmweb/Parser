<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\ReviewDTO;
use App\Models\Review;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    private const UPSERT_CHUNK_SIZE = 500;

    /**
     * @param  list<ReviewDTO>  $reviews
     */
    public function upsertBatch(int $organizationId, array $reviews): int
    {
        if ($reviews === []) {
            return 0;
        }

        $now = now();
        $affected = 0;

        foreach (array_chunk($reviews, self::UPSERT_CHUNK_SIZE) as $chunk) {
            $rows = [];

            foreach ($chunk as $review) {
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

            $affected += Review::query()->upsert(
                $rows,
                ['organization_id', 'yandex_review_id'],
                ['author_name', 'author_url', 'rating', 'text', 'reviewed_at', 'updated_at'],
            );
        }

        return $affected;
    }

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateByOrganization(int $organizationId, int $page, int $perPage = 50, ?int $rating = null): LengthAwarePaginator
    {
        $query = Review::query()->where('organization_id', $organizationId);

        if ($rating !== null) {
            $query->where('rating', $rating);
        }

        return $query
            ->orderByDesc('reviewed_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{1: int, 2: int, 3: int, 4: int, 5: int}
     */
    public function countByRating(int $organizationId): array
    {
        $counts = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ];

        $rows = Review::query()
            ->where('organization_id', $organizationId)
            ->whereIn('rating', [1, 2, 3, 4, 5])
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        foreach ($rows as $rating => $count) {
            $key = (int) $rating;

            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $count;
            }
        }

        return $counts;
    }
}
