<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OrganizationAlreadyParsingException;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ParseJobResource;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly OrganizationRepositoryInterface $organizationRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->apiError('Unauthenticated.', 401);
        }

        $page = max(1, $request->integer('page', 1));
        $paginator = $this->organizationRepository->paginateForUser($user->id, $page);

        return $this->apiSuccess([
            'data' => OrganizationResource::collection($paginator->getCollection())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->apiError('Unauthenticated.', 401);
        }

        $organization = $this->organizations->create($user, $request->organizationUrl());

        return $this->apiSuccess(new OrganizationResource($organization), 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        $organization->load('latestParseJob');

        return $this->apiSuccess(new OrganizationResource($organization));
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return $this->apiSuccess(['message' => 'Organization deleted']);
    }

    public function triggerParse(Organization $organization): JsonResponse
    {
        $this->authorize('parse', $organization);

        try {
            $parseJob = $this->organizations->dispatchParse($organization);
        } catch (OrganizationAlreadyParsingException $exception) {
            return $this->apiError($exception->getMessage(), 409);
        }

        return $this->apiSuccess(new ParseJobResource($parseJob), 202);
    }
}
