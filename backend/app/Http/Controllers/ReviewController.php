<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {}

    public function index(Organization $organization, Request $request): JsonResponse
    {
        $this->authorize('view', $organization);

        $page = max(1, $request->integer('page', 1));
        $paginator = $this->reviews->paginateByOrganization($organization->id, $page);

        return $this->apiSuccess([
            'data' => ReviewResource::collection($paginator->getCollection())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }
}
