<?php

namespace App\Services\Yandex;

use App\Contracts\OrganizationDataSource;
use App\DataTransferObjects\OrganizationData;
use App\DataTransferObjects\ReviewData;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

class YandexMapsDataSource implements OrganizationDataSource
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function fetch(string $url): OrganizationData
    {
        $url = $this->resolveReviewsUrl($url);
        $firstPage = $this->fetchPage($url, 1);
        $item = $this->findBusiness($this->decodeState($firstPage));

        if ($item === []) {
            throw new RuntimeException('На странице не найдены данные организации.');
        }

        $reviews = $this->mapReviews($item);
        $params = Arr::get($item, 'reviewResults.params', []);
        $totalPages = min(
            max((int) ($params['totalPages'] ?? 1), 1),
            min(max((int) config('yandex.max_pages', 12), 1), 12),
        );

        for ($page = 2; $page <= $totalPages; $page++) {
            $pageItem = $this->findBusiness(
                $this->decodeState($this->fetchPage($url, $page)),
            );
            $pageReviews = $this->mapReviews($pageItem);

            if ($pageReviews === []) {
                throw new RuntimeException("Яндекс.Карты не вернули отзывы на странице {$page}.");
            }

            array_push($reviews, ...$pageReviews);
        }

        $uniqueReviews = [];

        foreach ($reviews as $review) {
            $uniqueReviews[$review->externalId] ??= $review;
        }

        $reviews = array_values($uniqueReviews);
        $rating = Arr::get($item, 'ratingData');

        if (! is_array($rating)) {
            throw new RuntimeException('В ответе Яндекс.Карт отсутствуют данные рейтинга.');
        }

        return new OrganizationData(
            externalId: (string) $item['id'],
            name: (string) ($item['title'] ?? $item['shortTitle'] ?? 'Организация'),
            rating: round((float) ($rating['ratingValue'] ?? 0), 1),
            ratingsCount: (int) ($rating['ratingCount'] ?? 0),
            reviewsCount: (int) ($rating['reviewCount'] ?? 0),
            reviews: $reviews,
        );
    }

    private function resolveReviewsUrl(string $url): string
    {
        $normalized = $this->normalizeOrganizationUrl($url);

        if ($normalized !== null) {
            return $normalized;
        }

        $response = $this->request()->get($url)->throw();
        $effectiveUrl = $response->handlerStats()['url'] ?? null;

        if (! is_string($effectiveUrl)) {
            throw new RuntimeException('Не удалось раскрыть короткую ссылку Яндекс.Карт.');
        }

        $normalized = $this->normalizeOrganizationUrl($effectiveUrl);

        if ($normalized === null) {
            throw new RuntimeException('Ссылка не ведёт на карточку организации в Яндекс.Картах.');
        }

        return $normalized;
    }

    private function normalizeOrganizationUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        $this->assertAllowedHost($scheme, $host);

        if (! preg_match('#^(.*?/maps/(?:[^/]+/){0,3}org/[^/]+/\d+)#u', $path, $matches)) {
            return null;
        }

        return $scheme.'://'.$host.rtrim($matches[1], '/').'/reviews/';
    }

    private function assertAllowedHost(string $scheme, string $host): void
    {
        if (
            ! in_array(strtolower($scheme), ['http', 'https'], true)
            || ! preg_match('/(^|\.)yandex\.(ru|com|kz|by|uz|com\.tr)$/', strtolower($host))
        ) {
            throw new RuntimeException('Разрешены только ссылки на Яндекс.Карты.');
        }
    }

    private function fetchPage(string $url, int $page): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $pageUrl = $page === 1 ? $url : $url.$separator.'page='.$page;
        $response = $this->request()
            ->get($pageUrl)
            ->throw();

        if ($response->body() === '') {
            throw new RuntimeException('Яндекс.Карты вернули пустой ответ.');
        }

        return $response->body();
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'User-Agent' => self::USER_AGENT,
            ])
            ->connectTimeout((int) config('yandex.connect_timeout', 5))
            ->timeout((int) config('yandex.timeout', 20))
            ->retry(
                (int) config('yandex.retry_times', 2),
                (int) config('yandex.retry_delay', 500),
            )
            ->withOptions([
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => true,
                    'on_redirect' => function ($request, $response, $uri): void {
                        $this->assertAllowedHost($uri->getScheme(), $uri->getHost());
                    },
                ],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeState(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('Не удалось разобрать страницу Яндекс.Карт.');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(
            '//script[@type="application/json" and contains(concat(" ", normalize-space(@class), " "), " state-view ")]',
        );
        $json = $nodes?->item(0)?->textContent;

        if (! is_string($json) || trim($json) === '') {
            throw new RuntimeException('Яндекс.Карты изменили формат страницы.');
        }

        try {
            $state = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Яндекс.Карты вернули повреждённые данные.', previous: $exception);
        }

        if (! is_array($state)) {
            throw new RuntimeException('Яндекс.Карты вернули неожиданный формат данных.');
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function findBusiness(array $state): array
    {
        if (
            isset($state['id'], $state['reviewResults'])
            && is_array($state['reviewResults'])
        ) {
            return $state;
        }

        foreach ($state as $value) {
            if (! is_array($value)) {
                continue;
            }

            $business = $this->findBusiness($value);

            if ($business !== []) {
                return $business;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, ReviewData>
     */
    private function mapReviews(array $item): array
    {
        $reviews = Arr::get($item, 'reviewResults.reviews');

        if (! is_array($reviews)) {
            throw new RuntimeException('В ответе Яндекс.Карт отсутствует список отзывов.');
        }

        return array_map(function (array $review): ReviewData {
            $id = $review['reviewId'] ?? null;
            $rating = (int) ($review['rating'] ?? 0);
            $publishedAt = $review['updatedTime'] ?? $review['createdTime'] ?? null;

            if (! is_string($id) || $id === '' || $rating < 1 || $rating > 5 || ! is_string($publishedAt)) {
                throw new RuntimeException('Яндекс.Карты вернули отзыв с неполными данными.');
            }

            return new ReviewData(
                externalId: $id,
                author: (string) Arr::get($review, 'author.name', 'Аноним'),
                rating: $rating,
                text: isset($review['text']) && $review['text'] !== ''
                    ? (string) $review['text']
                    : null,
                publishedAt: new DateTimeImmutable($publishedAt),
            );
        }, $reviews);
    }
}
