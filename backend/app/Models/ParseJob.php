<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParseJobStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property ParseJobStatus $status
 * @property int $total_reviews
 * @property int $parsed_reviews
 * @property string|null $error_message
 * @property int $attempts
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable([
    'organization_id',
    'status',
    'total_reviews',
    'parsed_reviews',
    'error_message',
    'attempts',
    'started_at',
    'finished_at',
])]
class ParseJob extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'total_reviews' => 0,
        'parsed_reviews' => 0,
        'attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParseJobStatus::class,
            'total_reviews' => 'integer',
            'parsed_reviews' => 'integer',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
