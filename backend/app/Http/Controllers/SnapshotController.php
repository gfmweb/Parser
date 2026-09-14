<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SnapshotResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnapshotController extends Controller
{
    public function index(Organization $organization, Request $request): JsonResponse
    {
        $this->authorize('view', $organization);

        $page = max(1, $request->integer('page', 1));
        $paginator = $organization->snapshots()
            ->orderByDesc('snapshot_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page', $page);

        return $this->apiSuccess([
            'data' => SnapshotResource::collection($paginator->getCollection())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }
}
