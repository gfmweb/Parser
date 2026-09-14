<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     author_name: string|null,
     *     rating: int|null,
     *     text: string|null,
     *     reviewed_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'author_name' => $review->author_name,
            'rating' => $review->rating,
            'text' => $review->text,
            'reviewed_at' => $review->reviewed_at?->toIso8601String(),
        ];
    }
}
