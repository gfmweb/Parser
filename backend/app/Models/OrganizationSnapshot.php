<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type SnapshotDiff array<string, array{from: mixed, to: mixed}>
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $rating
 * @property int|null $rating_count
 * @property int|null $review_count
 * @property Carbon $snapshot_at
 * @property SnapshotDiff|null $diff
 * @property-read Organization $organization
 */
#[Fillable([
    'organization_id',
    'rating',
    'rating_count',
    'review_count',
    'snapshot_at',
    'diff',
])]
class OrganizationSnapshot extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'rating_count' => 'integer',
            'review_count' => 'integer',
            'snapshot_at' => 'datetime',
            'diff' => 'array',
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
