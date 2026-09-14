<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\DTOs\OrganizationDataDTO;
use App\Models\OrganizationSnapshot;

final class OrganizationSnapshotRecorder
{
    public function record(int $organizationId, OrganizationDataDTO $parsed): OrganizationSnapshot
    {
        $previous = OrganizationSnapshot::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('snapshot_at')
            ->orderByDesc('id')
            ->first();

        return OrganizationSnapshot::query()->create([
            'organization_id' => $organizationId,
            'rating' => $parsed->rating,
            'rating_count' => $parsed->ratingCount,
            'review_count' => $parsed->reviewCount,
            'snapshot_at' => now(),
            'diff' => $this->diff($previous, $parsed),
        ]);
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function diff(?OrganizationSnapshot $previous, OrganizationDataDTO $parsed): array
    {
        if ($previous === null) {
            return [];
        }

        $diff = [];
        $previousRating = $previous->rating !== null ? (float) $previous->rating : null;

        if (! $this->ratingsEqual($previousRating, $parsed->rating)) {
            $diff['rating'] = ['from' => $previousRating, 'to' => $parsed->rating];
        }

        if ($previous->rating_count !== $parsed->ratingCount) {
            $diff['rating_count'] = ['from' => $previous->rating_count, 'to' => $parsed->ratingCount];
        }

        if ($previous->review_count !== $parsed->reviewCount) {
            $diff['review_count'] = ['from' => $previous->review_count, 'to' => $parsed->reviewCount];
        }

        return $diff;
    }

    private function ratingsEqual(?float $previous, float $next): bool
    {
        if ($previous === null) {
            return false;
        }

        return abs($previous - $next) < 0.001;
    }
}
