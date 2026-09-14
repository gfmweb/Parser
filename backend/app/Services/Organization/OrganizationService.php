<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Enums\ParseJobStatus;
use App\Enums\ParseStatus;
use App\Exceptions\OrganizationAlreadyParsingException;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\ParseJob;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Repositories\Contracts\ParseJobRepositoryInterface;

final class OrganizationService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizations,
        private readonly ParseJobRepositoryInterface $parseJobs,
    ) {}

    public function create(User $user, string $url): Organization
    {
        $organization = $this->organizations->upsert($user->id, $url, [
            'parse_status' => ParseStatus::Pending,
        ]);

        $this->dispatchParse($organization);

        return $organization->load('latestParseJob');
    }

    public function dispatchParse(Organization $organization): ParseJob
    {
        if ($this->isCurrentlyParsing($organization)) {
            throw new OrganizationAlreadyParsingException;
        }

        $this->organizations->updateParseStatus($organization->id, ParseStatus::Pending->value);
        $parseJob = $this->parseJobs->createForOrganization($organization->id);
        ParseOrganizationJob::dispatch($organization->id, $parseJob->id);

        return $parseJob;
    }

    private function isCurrentlyParsing(Organization $organization): bool
    {
        if ($organization->parse_status === ParseStatus::Parsing) {
            return true;
        }

        $latest = $this->parseJobs->latestForOrganization($organization->id);

        return $latest !== null && in_array($latest->status, [ParseJobStatus::Queued, ParseJobStatus::Running], true);
    }
}
