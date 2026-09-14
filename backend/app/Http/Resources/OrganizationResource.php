<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Organization $organization */
        $organization = $this->resource;

        return [
            'id' => $organization->id,
            'yandex_url' => $organization->yandex_url,
            'yandex_id' => $organization->yandex_id,
            'name' => $organization->name,
            'address' => $organization->address,
            'rating' => $organization->rating !== null ? (float) $organization->rating : null,
            'rating_count' => $organization->rating_count,
            'review_count' => $organization->review_count,
            'parse_status' => $organization->parse_status->value,
            'parse_error' => $organization->parse_error,
            'last_parsed_at' => $organization->last_parsed_at?->toIso8601String(),
            'latest_parse_job' => $this->when(
                $organization->relationLoaded('latestParseJob'),
                fn (): ?ParseJobResource => $organization->latestParseJob !== null
                    ? new ParseJobResource($organization->latestParseJob)
                    : null,
            ),
        ];
    }
}
