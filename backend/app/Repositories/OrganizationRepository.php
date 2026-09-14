<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\OrganizationDataDTO;
use App\Enums\ParseStatus;
use App\Models\Organization;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    public function findById(int $id): Organization
    {
        return Organization::query()->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator<int, Organization>
     */
    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return Organization::query()
            ->where('user_id', $userId)
            ->with('latestParseJob')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByUserAndUrl(int $userId, string $url): ?Organization
    {
        return Organization::query()
            ->where('user_id', $userId)
            ->where('yandex_url', $url)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(int $userId, string $url, array $data): Organization
    {
        return Organization::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'yandex_url' => $url,
            ],
            $data,
        );
    }

    public function applyParsedData(int $id, OrganizationDataDTO $parsed): void
    {
        Organization::query()->whereKey($id)->update([
            'yandex_id' => $parsed->yandexId,
            'name' => $parsed->name,
            'address' => $parsed->address,
            'rating' => $parsed->rating,
            'rating_count' => $parsed->ratingCount,
            'review_count' => $parsed->reviewCount,
            'parse_status' => ParseStatus::Done,
            'parse_error' => null,
            'last_parsed_at' => now(),
        ]);
    }

    public function updateParseStatus(int $id, string $status, ?string $error = null): void
    {
        $parseStatus = ParseStatus::from($status);

        $attributes = [
            'parse_status' => $parseStatus,
            'parse_error' => $error,
        ];

        if ($parseStatus === ParseStatus::Done) {
            $attributes['last_parsed_at'] = now();
        }

        Organization::query()->whereKey($id)->update($attributes);
    }
}
