<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $yandex_url
 * @property string|null $yandex_id
 * @property string|null $name
 * @property string|null $address
 * @property string|null $rating
 * @property int $rating_count
 * @property int $review_count
 * @property ParseStatus $parse_status
 * @property Carbon|null $last_parsed_at
 * @property string|null $parse_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Review> $reviews
 * @property-read Collection<int, OrganizationSnapshot> $snapshots
 * @property-read Collection<int, ParseJob> $parseJobs
 * @property-read ParseJob|null $latestParseJob
 */
#[Fillable([
    'user_id',
    'yandex_url',
    'yandex_id',
    'name',
    'address',
    'rating',
    'rating_count',
    'review_count',
    'parse_status',
    'last_parsed_at',
    'parse_error',
])]
class Organization extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rating_count' => 0,
        'review_count' => 0,
        'parse_status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'rating_count' => 'integer',
            'review_count' => 'integer',
            'parse_status' => ParseStatus::class,
            'last_parsed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<OrganizationSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class);
    }

    /**
     * @return HasMany<ParseJob, $this>
     */
    public function parseJobs(): HasMany
    {
        return $this->hasMany(ParseJob::class);
    }

    /**
     * @return HasOne<ParseJob, $this>
     */
    public function latestParseJob(): HasOne
    {
        return $this->hasOne(ParseJob::class)->latestOfMany();
    }
}
