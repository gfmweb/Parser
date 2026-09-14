<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class OrganizationDataDTO
{
    /**
     * @param  list<ReviewDTO>  $reviews
     */
    public function __construct(
        public string $yandexId,
        public string $name,
        public string $address,
        public float $rating,
        public int $ratingCount,
        public int $reviewCount,
        public array $reviews,
    ) {}
}
