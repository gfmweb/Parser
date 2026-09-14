<?php

declare(strict_types=1);

namespace App\Services\Parser;

use App\DTOs\OrganizationDataDTO;
use App\DTOs\ReviewDTO;
use App\Services\Parser\Contracts\ParserInterface;
use App\Services\Parser\Exceptions\OrganizationNotFoundException;
use App\Services\Parser\Exceptions\SourceChangedException;
use App\Services\Parser\Mappers\YandexReviewMapper;

final class YandexMapsParser implements ParserInterface
{
    private const REVIEW_PAGE_SIZE = 50;

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
        $reviews = [];
        $offset = 0;
        $state = $this->client->getReviews($orgId, $offset, self::REVIEW_PAGE_SIZE);
        $meta = $this->extractOrgMeta($state);

        while (true) {
            $page = $this->mapper->map($this->reviewResults($state));

            foreach ($page as $review) {
                $reviews[] = $review;
            }

            if ($onProgress !== null && $reviews !== []) {
                $onProgress(count($reviews), $this->progressTotal($meta['reviewCount'], count($reviews)));
            }

            if ($this->isLastReviewsPage($state)) {
                break;
            }

            usleep(random_int(500_000, 1_500_000));
            $offset += self::REVIEW_PAGE_SIZE;
            $state = $this->client->getReviews($orgId, $offset, self::REVIEW_PAGE_SIZE);
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
        return $this->mapper->map($this->reviewResults($this->client->getReviews($orgId, $offset, $limit)));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{name: string, address: string, rating: float, ratingCount: int, reviewCount: int}
     */
    private function extractOrgMeta(array $state): array
    {
        $item = $this->orgItem($state);
        $name = $item['title'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new SourceChangedException('stack.0.results.items.0.title', $state);
        }

        $ratingBlock = $item['ratingData'] ?? null;

        if (! is_array($ratingBlock)) {
            throw new SourceChangedException('stack.0.results.items.0.ratingData', $state);
        }

        $ratingValue = $ratingBlock['ratingValue'] ?? null;

        if (! is_numeric($ratingValue)) {
            throw new SourceChangedException('stack.0.results.items.0.ratingData.ratingValue', $state);
        }

        $ratingCount = $ratingBlock['ratingCount'] ?? null;

        if (! is_numeric($ratingCount)) {
            throw new SourceChangedException('stack.0.results.items.0.ratingData.ratingCount', $state);
        }

        $address = $item['address'] ?? '';
        $reviewCount = $ratingBlock['reviewCount'] ?? 0;

        return [
            'name' => $name,
            'address' => is_string($address) ? $address : '',
            'rating' => (float) $ratingValue,
            'ratingCount' => (int) $ratingCount,
            'reviewCount' => is_numeric($reviewCount) ? (int) $reviewCount : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function orgItem(array $state): array
    {
        $stack = $state['stack'] ?? null;

        if (! is_array($stack) || ! isset($stack[0]) || ! is_array($stack[0])) {
            throw new SourceChangedException('stack.0', $state);
        }

        $results = $stack[0]['results'] ?? null;

        if (! is_array($results)) {
            throw new SourceChangedException('stack.0.results', $state);
        }

        $items = $results['items'] ?? null;

        if (! is_array($items)) {
            throw new SourceChangedException('stack.0.results.items', $state);
        }

        if ($items === []) {
            throw new OrganizationNotFoundException('Organization not found.');
        }

        $item = $items[0] ?? null;

        if (! is_array($item)) {
            throw new SourceChangedException('stack.0.results.items.0', $state);
        }

        /** @var array<string, mixed> $item */
        return $item;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function reviewResults(array $state): array
    {
        $item = $this->orgItem($state);
        $reviewResults = $item['reviewResults'] ?? null;

        if (! is_array($reviewResults)) {
            throw new SourceChangedException('stack.0.results.items.0.reviewResults', $state);
        }

        /** @var array<string, mixed> $reviewResults */
        return $reviewResults;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function isLastReviewsPage(array $state): bool
    {
        $reviewResults = $this->reviewResults($state);
        $params = $reviewResults['params'] ?? null;

        if (is_array($params)) {
            $page = $params['page'] ?? null;
            $totalPages = $params['totalPages'] ?? null;

            if (is_numeric($page) && is_numeric($totalPages) && (int) $page >= (int) $totalPages) {
                return true;
            }
        }

        $rawReviews = $reviewResults['reviews'] ?? null;

        return is_array($rawReviews) && count($rawReviews) < self::REVIEW_PAGE_SIZE;
    }
}
