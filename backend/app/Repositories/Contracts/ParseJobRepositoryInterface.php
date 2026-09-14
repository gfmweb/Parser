<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ParseJob;

interface ParseJobRepositoryInterface
{
    public function createForOrganization(int $organizationId): ParseJob;

    public function latestForOrganization(int $organizationId): ?ParseJob;

    public function markRunning(int $jobId): void;

    public function updateProgress(int $jobId, int $parsed, int $total): void;

    public function markDone(int $jobId): void;

    public function markFailed(int $jobId, string $error): void;
}
