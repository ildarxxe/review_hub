<?php

namespace App\DataTransferObjects;

use DateTimeImmutable;

readonly class ReviewData
{
    public function __construct(
        public string $externalId,
        public string $author,
        public int $rating,
        public ?string $text,
        public DateTimeImmutable $publishedAt,
    ) {}
}
