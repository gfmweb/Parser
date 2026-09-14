<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

readonly class ReviewDTO
{
    public function __construct(
        public string $yandexReviewId,
        public string $authorName,
        public ?string $authorUrl,
        public int $rating,
        public ?string $text,
        public DateTimeImmutable $reviewedAt,
    ) {
        if ($this->rating < 1 || $this->rating > 5) {
            throw new InvalidArgumentException('Rating must be between 1 and 5.');
        }
    }
}
