<?php

namespace Tests\Feature;

use App\Services\Yandex\YandexMapsDataSource;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class YandexMapsDataSourceTest extends TestCase
{
    public function test_it_loads_review_pages_and_maps_organization_data(): void
    {
        config()->set('yandex.max_pages', 2);
        Http::fake(function (Request $request) {
            return match (true) {
                str_contains($request->url(), 'page=2') => Http::response($this->page([
                    $this->review('review-2', 'Ivan', 4, null, '2026-05-09T09:30:00Z'),
                    $this->review('review-1', 'Anna', 5, 'Duplicate', '2026-05-08T09:30:00Z'),
                ], page: 2, totalPages: 3)),
                default => Http::response($this->page([
                    $this->review('review-1', 'Anna', 5, 'Great service', '2026-05-10T12:00:00Z'),
                ], page: 1, totalPages: 3)),
            };
        });

        $data = $this->source()->fetch(
            'https://yandex.ru/maps/213/moscow/org/example/123/photos/?ll=37.1%2C55.2',
        );

        $this->assertSame('123', $data->externalId);
        $this->assertSame('Example company', $data->name);
        $this->assertSame(4.8, $data->rating);
        $this->assertSame(328, $data->ratingsCount);
        $this->assertSame(92, $data->reviewsCount);
        $this->assertCount(2, $data->reviews);
        $this->assertSame('review-1', $data->reviews[0]->externalId);
        $this->assertSame('review-2', $data->reviews[1]->externalId);
        $this->assertSame('Ivan', $data->reviews[1]->author);
        $this->assertNull($data->reviews[1]->text);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://yandex.ru/maps/213/moscow/org/example/123/reviews/');
        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://yandex.ru/maps/213/moscow/org/example/123/reviews/?page=2');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'page=3'));
    }

    public function test_it_normalizes_shared_map_url_with_poi_organization(): void
    {
        Http::fake([
            '*' => Http::response($this->page([
                $this->review('review-1', 'Anna', 5, 'Great service', '2026-05-10T12:00:00Z'),
            ], page: 1, totalPages: 1)),
        ]);
        $url = 'https://yandex.kz/maps/10295/kostanai/?ll=63.616495%2C53.217643'
            .'&mode=poi&poi%5Bpoint%5D=63.569732%2C53.219686'
            .'&poi%5Buri%5D=ymapsbm1%3A%2F%2Forg%3Foid%3D162508758578'
            .'&utm_source=share&z=14';

        $this->source()->fetch($url);

        Http::assertSent(fn (Request $request) => $request->url()
            === 'https://yandex.kz/maps/org/-/162508758578/reviews/');
    }

    public function test_it_loads_at_most_six_hundred_reviews(): void
    {
        config()->set('yandex.max_pages', 99);

        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            $reviews = [];

            for ($position = 1; $position <= 50; $position++) {
                $number = (($page - 1) * 50) + $position;
                $reviews[] = $this->review(
                    "review-{$number}",
                    "Author {$number}",
                    ($number % 5) + 1,
                    "Review {$number}",
                    '2026-05-10T12:00:00Z',
                );
            }

            return Http::response($this->page($reviews, page: $page, totalPages: 20));
        });

        $data = $this->source()->fetch('https://yandex.ru/maps/org/example/123');

        $this->assertCount(600, $data->reviews);
        $this->assertSame('review-1', $data->reviews[0]->externalId);
        $this->assertSame('review-600', $data->reviews[599]->externalId);
        Http::assertSentCount(12);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'page=13'));
    }

    public function test_it_reports_an_empty_review_page(): void
    {
        config()->set('yandex.max_pages', 2);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'page=2')) {
                return Http::response($this->page([], page: 2, totalPages: 2));
            }

            return Http::response($this->page([
                $this->review('review-1', 'Anna', 5, 'Great service', '2026-05-10T12:00:00Z'),
            ], page: 1, totalPages: 2));
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Яндекс.Карты не вернули отзывы на странице 2.');

        $this->source()->fetch('https://yandex.ru/maps/org/example/123');
    }

    public function test_it_reports_incomplete_review_data(): void
    {
        Http::fake([
            '*' => Http::response($this->page([[
                'reviewId' => 'review-1',
                'author' => ['name' => 'Anna'],
                'rating' => 0,
                'text' => 'Invalid rating',
                'updatedTime' => '2026-05-10T12:00:00Z',
            ]], page: 1, totalPages: 1)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Яндекс.Карты вернули отзыв с неполными данными.');

        $this->source()->fetch('https://yandex.ru/maps/org/example/123');
    }

    public function test_it_rejects_non_yandex_urls(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Разрешены только ссылки на Яндекс.Карты.');

        $this->source()->fetch('https://example.com/maps/org/company/123');
    }

    public function test_it_reports_changed_page_markup(): void
    {
        Http::fake([
            '*' => Http::response('<html><body>No state here</body></html>'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Яндекс.Карты изменили формат страницы.');

        $this->source()->fetch('https://yandex.kz/maps/org/company/123');
    }

    private function source(): YandexMapsDataSource
    {
        return new YandexMapsDataSource(app(HttpFactory::class));
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     */
    private function page(array $reviews, int $page, int $totalPages): string
    {
        $state = [
            'stack' => [[
                'results' => [
                    'items' => [[
                        'id' => '123',
                        'title' => 'Example company',
                        'ratingData' => [
                            'ratingCount' => 328,
                            'ratingValue' => 4.8000001,
                            'reviewCount' => 92,
                        ],
                        'reviewResults' => [
                            'reviews' => $reviews,
                            'params' => [
                                'page' => $page,
                                'totalPages' => $totalPages,
                            ],
                        ],
                    ]],
                ],
            ]],
        ];

        return '<!doctype html><html><body>'
            .'<script type="application/json" class="state-view">'
            .json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            .'</script></body></html>';
    }

    /**
     * @return array<string, mixed>
     */
    private function review(
        string $id,
        string $author,
        int $rating,
        ?string $text,
        string $updatedTime,
    ): array {
        return [
            'reviewId' => $id,
            'author' => ['name' => $author],
            'rating' => $rating,
            'text' => $text,
            'updatedTime' => $updatedTime,
        ];
    }
}
