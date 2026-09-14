<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $yandex_review_id
 * @property string|null $author_name
 * @property string|null $author_url
 * @property int|null $rating
 * @property string|null $text
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable([
    'organization_id',
    'yandex_review_id',
    'author_name',
    'author_url',
    'rating',
    'text',
    'reviewed_at',
    'first_seen_at',
])]
class Review extends Model
{
    public const CREATED_AT = 'first_seen_at';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'datetime',
            'first_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
