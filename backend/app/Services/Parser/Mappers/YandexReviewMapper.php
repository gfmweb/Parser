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
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new SourceChangedException('data', $payload);
        }

        $reviews = $data['reviews'] ?? null;

        if (! is_array($reviews)) {
            throw new SourceChangedException('data.reviews', $payload);
        }

        $mapped = [];

        foreach ($reviews as $index => $review) {
            if (! is_array($review)) {
                throw new SourceChangedException("data.reviews.{$index}", $payload);
            }

            $mapped[] = $this->mapReview($review, $payload);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $review
     * @param  array<string, mixed>  $payload
     */
    private function mapReview(array $review, array $payload): ReviewDTO
    {
        $id = $review['id'] ?? null;

        if (! is_string($id) && ! is_int($id)) {
            throw new SourceChangedException('reviews[].id', $payload);
        }

        $rating = $review['rating'] ?? null;

        if (! is_int($rating) && ! is_float($rating) && ! is_numeric($rating)) {
            throw new SourceChangedException('reviews[].rating', $payload);
        }

        $createdTime = $review['createdTime'] ?? null;

        if (! is_string($createdTime) || $createdTime === '') {
            throw new SourceChangedException('reviews[].createdTime', $payload);
        }

        try {
            $reviewedAt = new DateTimeImmutable($createdTime);
        } catch (Exception $exception) {
            throw new SourceChangedException('reviews[].createdTime', $payload, 0, $exception);
        }

        $author = is_array($review['author'] ?? null) ? $review['author'] : [];
        $authorName = $author['name'] ?? '';
        $authorUrl = $author['uri'] ?? null;

        return new ReviewDTO(
            yandexReviewId: (string) $id,
            authorName: is_string($authorName) ? $authorName : '',
            authorUrl: is_string($authorUrl) ? $authorUrl : null,
            rating: (int) $rating,
            text: is_string($review['text'] ?? null) ? $review['text'] : null,
            reviewedAt: $reviewedAt,
        );
    }
}
