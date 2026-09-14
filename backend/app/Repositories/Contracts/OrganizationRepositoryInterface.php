<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\OrganizationDataDTO;
use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrganizationRepositoryInterface
{
    public function findById(int $id): Organization;

    /**
     * @return LengthAwarePaginator<int, Organization>
     */
    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): LengthAwarePaginator;

    public function findByUserAndUrl(int $userId, string $url): ?Organization;

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(int $userId, string $url, array $data): Organization;

    public function applyParsedData(int $id, OrganizationDataDTO $parsed): void;

    public function updateParseStatus(int $id, string $status, ?string $error = null): void;
}
