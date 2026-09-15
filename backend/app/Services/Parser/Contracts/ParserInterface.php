<?php

declare(strict_types=1);

namespace App\Services\Parser\Contracts;

use App\DTOs\OrganizationDataDTO;
use App\DTOs\OrganizationMetaDTO;
use App\DTOs\ReviewDTO;

interface ParserInterface
{
    /**
     * @param  (callable(int $parsed, int $total): void)|null  $onProgress
     * @param  (callable(OrganizationMetaDTO $meta): void)|null  $onMeta
     */
    public function parse(string $url, ?callable $onProgress = null, ?callable $onMeta = null): OrganizationDataDTO;

    /**
     * @return list<ReviewDTO>
     */
    public function parseReviewsPage(string $orgId, int $offset, int $limit): array;
}
