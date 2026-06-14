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
