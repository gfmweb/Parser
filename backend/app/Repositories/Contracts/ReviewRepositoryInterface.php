<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\ReviewDTO;
use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    /**
     * @param  list<ReviewDTO>  $reviews
     */
    public function upsertBatch(int $organizationId, array $reviews): int;

    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function paginateByOrganization(int $organizationId, int $page, int $perPage = 50, ?int $rating = null): LengthAwarePaginator;

    /**
     * @return array{1: int, 2: int, 3: int, 4: int, 5: int}
     */
    public function countByRating(int $organizationId): array;
}
