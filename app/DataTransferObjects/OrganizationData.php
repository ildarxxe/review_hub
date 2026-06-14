<?php

namespace App\DataTransferObjects;

readonly class OrganizationData
{
    /**
     * @param  array<int, ReviewData>  $reviews
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
        public array $reviews,
    ) {}
}
