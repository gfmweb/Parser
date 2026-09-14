<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ParseJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParseJob
 */
class ParseJobResource extends JsonResource
{
    /**
     * @return array{
     *     status: string,
     *     total_reviews: int,
     *     parsed_reviews: int,
     *     error_message: string|null,
     *     started_at: string|null,
     *     finished_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var ParseJob $parseJob */
        $parseJob = $this->resource;

        return [
            'status' => $parseJob->status->value,
            'total_reviews' => $parseJob->total_reviews,
            'parsed_reviews' => $parseJob->parsed_reviews,
            'error_message' => $parseJob->error_message,
            'started_at' => $parseJob->started_at?->toIso8601String(),
            'finished_at' => $parseJob->finished_at?->toIso8601String(),
        ];
    }
}
