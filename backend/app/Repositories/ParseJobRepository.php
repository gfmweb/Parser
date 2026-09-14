<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ParseJobStatus;
use App\Models\ParseJob;
use App\Repositories\Contracts\ParseJobRepositoryInterface;

class ParseJobRepository implements ParseJobRepositoryInterface
{
    public function createForOrganization(int $organizationId): ParseJob
    {
        return ParseJob::query()->create([
            'organization_id' => $organizationId,
            'status' => ParseJobStatus::Queued,
        ]);
    }

    public function latestForOrganization(int $organizationId): ?ParseJob
    {
        return ParseJob::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('id')
            ->first();
    }

    public function markRunning(int $jobId): void
    {
        ParseJob::query()->whereKey($jobId)->update([
            'status' => ParseJobStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ]);
    }

    public function updateProgress(int $jobId, int $parsed, int $total): void
    {
        ParseJob::query()->whereKey($jobId)->update([
            'parsed_reviews' => $parsed,
            'total_reviews' => $total,
        ]);
    }

    public function markDone(int $jobId): void
    {
        ParseJob::query()->whereKey($jobId)->update([
            'status' => ParseJobStatus::Done,
            'finished_at' => now(),
        ]);
    }

    public function markFailed(int $jobId, string $error): void
    {
        ParseJob::query()->whereKey($jobId)->increment(
            'attempts',
            1,
            [
                'status' => ParseJobStatus::Failed,
                'error_message' => $error,
                'finished_at' => now(),
            ],
        );
    }
}
