<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrganizationSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationSnapshot
 */
class SnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrganizationSnapshot $snapshot */
        $snapshot = $this->resource;

        return [
            'id' => $snapshot->id,
            'rating' => $snapshot->rating !== null ? (float) $snapshot->rating : null,
            'rating_count' => $snapshot->rating_count,
            'review_count' => $snapshot->review_count,
            'snapshot_at' => $snapshot->snapshot_at->toIso8601String(),
            'diff' => $snapshot->diff,
        ];
    }
}
