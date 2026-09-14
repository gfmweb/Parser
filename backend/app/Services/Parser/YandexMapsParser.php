<?php

declare(strict_types=1);

namespace App\Services\Parser;

use App\DTOs\OrganizationDataDTO;
use App\DTOs\ReviewDTO;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\Parser\Exceptions\SourceChangedException;
use App\Services\Parser\Mappers\YandexReviewMapper;

final class YandexMapsParser implements ParserInterface
{
    private const REVIEW_PAGE_SIZE = 10;

    public function __construct(
        private readonly YandexApiClient $client,
        private readonly YandexUrlParser $urlParser,
        private readonly YandexReviewMapper $mapper,
    ) {}

    /**
     * @param  (callable(int $parsed, int $total): void)|null  $onProgress
     */
    public function parse(string $url, ?callable $onProgress = null): OrganizationDataDTO
    {
        $orgId = $this->urlParser->extractOrgId($url);
        $info = $this->client->getOrgInfo($orgId);
        $meta = $this->extractOrgMeta($info);

        $reviews = [];
        $offset = 0;
        $isFirstPage = true;

        while (true) {
            if (! $isFirstPage) {
                usleep(random_int(500_000, 1_500_000));
            }

            $isFirstPage = false;
            $page = $this->parseReviewsPage($orgId, $offset, self::REVIEW_PAGE_SIZE);

            if ($page === []) {
                break;
            }

            foreach ($page as $review) {
                $reviews[] = $review;
            }

            $offset += self::REVIEW_PAGE_SIZE;

            if ($onProgress !== null) {
                $onProgress(count($reviews), $this->progressTotal($meta['reviewCount'], count($reviews)));
            }
        }

        $reviewCount = $meta['reviewCount'] > 0 ? $meta['reviewCount'] : count($reviews);

        return new OrganizationDataDTO(
            yandexId: $orgId,
            name: $meta['name'],
            address: $meta['address'],
            rating: $meta['rating'],
            ratingCount: $meta['ratingCount'],
            reviewCount: $reviewCount,
            reviews: $reviews,
        );
    }

    private function progressTotal(int $declaredReviewCount, int $parsedCount): int
    {
        return $declaredReviewCount > 0 ? max($declaredReviewCount, $parsedCount) : $parsedCount;
    }

    /**
     * @return list<ReviewDTO>
     */
    public function parseReviewsPage(string $orgId, int $offset, int $limit): array
    {
        return $this->mapper->map($this->client->getReviews($orgId, $offset, $limit));
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array{name: string, address: string, rating: float, ratingCount: int, reviewCount: int}
     */
    private function extractOrgMeta(array $info): array
    {
        $data = $info['data'] ?? null;

        if (! is_array($data)) {
            throw new SourceChangedException('data', $info);
        }

        $name = $data['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new SourceChangedException('data.name', $info);
        }

        $ratingBlock = $data['rating'] ?? null;

        if (! is_array($ratingBlock)) {
            throw new SourceChangedException('data.rating', $info);
        }

        $ratingValue = $ratingBlock['value'] ?? null;

        if (! is_numeric($ratingValue)) {
            throw new SourceChangedException('data.rating.value', $info);
        }

        $ratingCount = $ratingBlock['count'] ?? null;

        if (! is_numeric($ratingCount)) {
            throw new SourceChangedException('data.rating.count', $info);
        }

        $address = $data['address'] ?? '';
        $reviewCount = $data['reviewCount'] ?? $data['reviewsCount'] ?? 0;

        return [
            'name' => $name,
            'address' => is_string($address) ? $address : '',
            'rating' => (float) $ratingValue,
            'ratingCount' => (int) $ratingCount,
            'reviewCount' => is_numeric($reviewCount) ? (int) $reviewCount : 0,
        ];
    }
}
