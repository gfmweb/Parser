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
    public function paginateByOrganization(int $organizationId, int $page, int $perPage = 50): LengthAwarePaginator;
}
