<?php

declare(strict_types=1);

namespace App\Services\Parser\Mappers;

use App\DTOs\ReviewDTO;
use App\Services\Parser\Exceptions\SourceChangedException;
use DateTimeImmutable;
use Exception;

class YandexReviewMapper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<ReviewDTO>
     */
    public function map(array $payload): array
    {
        $reviews = $this->extractReviews($payload);
        $mapped = [];

        foreach ($reviews as $index => $review) {
            if (! is_array($review)) {
                throw new SourceChangedException("reviewResults.reviews.{$index}", $payload);
            }

            $mappedReview = $this->mapReview($review, $payload);

            if ($mappedReview !== null) {
                $mapped[] = $mappedReview;
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    private function extractReviews(array $payload): array
    {
        if (isset($payload['reviews']) && is_array($payload['reviews'])) {
            return array_values($payload['reviews']);
        }

        $data = $payload['data'] ?? null;

        if (is_array($data) && isset($data['reviews']) && is_array($data['reviews'])) {
            return array_values($data['reviews']);
        }

        throw new SourceChangedException('reviewResults.reviews', $payload);
    }

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>  $payload
     */
    private function mapReview(array $review, array $payload): ?ReviewDTO
    {
        $id = $review['reviewId'] ?? $review['id'] ?? null;

        if (! is_string($id) && ! is_int($id)) {
            throw new SourceChangedException('reviews[].reviewId', $payload);
        }

        $rating = $review['rating'] ?? null;

        if (! is_int($rating) && ! is_float($rating) && ! is_numeric($rating)) {
            throw new SourceChangedException('reviews[].rating', $payload);
        }

        $ratingInt = (int) $rating;

        // Яндекс иногда отдаёт 0 (оценка не выставлена) — в DTO и CHECK допустимы только 1–5.
        if ($ratingInt < 1 || $ratingInt > 5) {
            return null;
        }

        $createdTime = $review['updatedTime'] ?? $review['createdTime'] ?? null;

        if (! is_string($createdTime) || $createdTime === '') {
            throw new SourceChangedException('reviews[].updatedTime', $payload);
        }

        try {
            $reviewedAt = new DateTimeImmutable($createdTime);
        } catch (Exception $exception) {
            throw new SourceChangedException('reviews[].updatedTime', $payload, 0, $exception);
        }

        $author = is_array($review['author'] ?? null) ? $review['author'] : [];
        $authorName = $author['name'] ?? '';
        $authorUrl = $this->authorUrl($author);

        return new ReviewDTO(
            yandexReviewId: (string) $id,
            authorName: is_string($authorName) ? $authorName : '',
            authorUrl: $authorUrl,
            rating: $ratingInt,
            text: is_string($review['text'] ?? null) ? $review['text'] : null,
            reviewedAt: $reviewedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $author
     */
    private function authorUrl(array $author): ?string
    {
        $uri = $author['uri'] ?? null;

        if (is_string($uri) && $uri !== '') {
            return $uri;
        }

        $publicId = $author['publicId'] ?? null;

        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return 'https://yandex.ru/maps/user/'.$publicId;
    }
}
