<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\IndexReviewsRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {}

    public function index(Organization $organization, IndexReviewsRequest $request): JsonResponse
    {
        $this->authorize('view', $organization);

        $paginator = $this->reviews->paginateByOrganization(
            $organization->id,
            $request->page(),
            50,
            $request->rating(),
        );

        return $this->apiSuccess([
            'data' => ReviewResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                ...$this->paginationMeta($paginator),
                'rating_counts' => $this->reviews->countByRating($organization->id),
            ],
        ]);
    }
}
