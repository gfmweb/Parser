<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ParseJobResource;
use App\Models\Organization;
use App\Repositories\Contracts\ParseJobRepositoryInterface;
use Illuminate\Http\JsonResponse;

class ParseJobController extends Controller
{
    public function __construct(
        private readonly ParseJobRepositoryInterface $parseJobs,
    ) {}

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        $parseJob = $this->parseJobs->latestForOrganization($organization->id);

        if ($parseJob === null) {
            return $this->apiError('Задача парсинга не найдена.', 404);
        }

        return $this->apiSuccess(new ParseJobResource($parseJob));
    }
}
