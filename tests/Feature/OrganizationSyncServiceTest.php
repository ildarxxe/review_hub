<?php

namespace Tests\Feature;

use App\Contracts\OrganizationDataSource;
use App\DataTransferObjects\OrganizationData;
use App\DataTransferObjects\ReviewData;
use App\Exceptions\OrganizationSyncException;
use App\Models\User;
use App\Services\OrganizationSyncService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrganizationSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_replaces_cached_reviews_and_updates_organization(): void
    {
        $user = User::factory()->create();
        $organization = $user->organization()->create([
            'source_url' => 'https://yandex.ru/maps/org/company/123',
            'sync_status' => 'pending',
        ]);
        $organization->reviews()->create([
            'external_id' => 'old-review',
            'position' => 0,
            'author' => 'Old author',
            'rating' => 1,
            'text' => 'Outdated review',
            'published_at' => new DateTimeImmutable('2025-01-01'),
        ]);

        $source = new class implements OrganizationDataSource
        {
            public function fetch(string $url): OrganizationData
            {
                return new OrganizationData(
                    externalId: '123',
                    name: 'Example company',
                    rating: 4.7,
                    ratingsCount: 328,
                    reviewsCount: 2,
                    reviews: [
                        new ReviewData(
                            externalId: 'review-1',
                            author: 'Anna',
                            rating: 5,
                            text: 'Great service',
                            publishedAt: new DateTimeImmutable('2026-05-10 12:00:00'),
                        ),
                        new ReviewData(
                            externalId: 'review-2',
                            author: 'Ivan',
                            rating: 4,
                            text: null,
                            publishedAt: new DateTimeImmutable('2026-05-09 09:30:00'),
                        ),
                    ],
                );
            }
        };

        $result = (new OrganizationSyncService($source))->sync($organization);

        $this->assertSame('ready', $result->sync_status);
        $this->assertSame('Example company', $result->name);
        $this->assertSame('4.7', $result->rating);
        $this->assertSame(328, $result->ratings_count);
        $this->assertSame(2, $result->reviews_count);
        $this->assertNotNull($result->synced_at);
        $this->assertDatabaseMissing('reviews', ['external_id' => 'old-review']);
        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'review-1',
            'position' => 0,
            'author' => 'Anna',
            'rating' => 5,
        ]);
    }

    public function test_stale_sync_does_not_overwrite_new_organization_url(): void
    {
        $user = User::factory()->create();
        $organization = $user->organization()->create([
            'source_url' => 'https://yandex.ru/maps/org/old-company/123',
            'sync_status' => 'pending',
        ]);
        $organization->reviews()->create([
            'external_id' => 'cached-review',
            'position' => 0,
            'author' => 'Anna',
            'rating' => 5,
            'text' => 'Cached text',
            'published_at' => new DateTimeImmutable('2026-05-10 12:00:00'),
        ]);

        $source = new class($organization->id) implements OrganizationDataSource
        {
            public function __construct(
                private readonly int $organizationId,
            ) {}

            public function fetch(string $url): OrganizationData
            {
                \App\Models\Organization::query()
                    ->whereKey($this->organizationId)
                    ->update([
                        'source_url' => 'https://yandex.ru/maps/org/new-company/456',
                        'sync_status' => 'pending',
                    ]);

                return new OrganizationData(
                    externalId: '123',
                    name: 'Old company',
                    rating: 4.0,
                    ratingsCount: 10,
                    reviewsCount: 0,
                    reviews: [],
                );
            }
        };

        $result = (new OrganizationSyncService($source))->sync(
            $organization,
            'https://yandex.ru/maps/org/old-company/123',
        );

        $this->assertSame('https://yandex.ru/maps/org/new-company/456', $result->source_url);
        $this->assertSame('pending', $result->sync_status);
        $this->assertNull($result->name);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'cached-review',
        ]);
    }

    public function test_storage_failure_rolls_back_cached_reviews(): void
    {
        $user = User::factory()->create();
        $organization = $user->organization()->create([
            'source_url' => 'https://yandex.ru/maps/org/company/123',
            'sync_status' => 'ready',
        ]);
        $organization->reviews()->create([
            'external_id' => 'cached-review',
            'position' => 0,
            'author' => 'Anna',
            'rating' => 5,
            'text' => 'Cached text',
            'published_at' => new DateTimeImmutable('2026-05-10 12:00:00'),
        ]);

        $duplicateReview = new ReviewData(
            externalId: 'duplicate-id',
            author: 'Ivan',
            rating: 4,
            text: 'Review',
            publishedAt: new DateTimeImmutable('2026-05-09 09:30:00'),
        );
        $source = new class($duplicateReview) implements OrganizationDataSource
        {
            public function __construct(
                private readonly ReviewData $review,
            ) {}

            public function fetch(string $url): OrganizationData
            {
                return new OrganizationData(
                    externalId: '123',
                    name: 'Example company',
                    rating: 4.0,
                    ratingsCount: 10,
                    reviewsCount: 2,
                    reviews: [$this->review, $this->review],
                );
            }
        };

        try {
            (new OrganizationSyncService($source))->sync($organization);
            $this->fail('Sync exception was not thrown.');
        } catch (OrganizationSyncException $exception) {
            $this->assertSame('Не удалось сохранить полученные отзывы.', $exception->getMessage());
        }

        $organization->refresh();

        $this->assertSame('failed', $organization->sync_status);
        $this->assertSame('Не удалось сохранить полученные отзывы.', $organization->sync_error);
        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'cached-review',
        ]);
    }

    public function test_source_failure_keeps_cached_reviews_and_marks_sync_as_failed(): void
    {
        $user = User::factory()->create();
        $organization = $user->organization()->create([
            'source_url' => 'https://yandex.ru/maps/org/company/123',
            'name' => 'Cached company',
            'sync_status' => 'ready',
        ]);
        $organization->reviews()->create([
            'external_id' => 'cached-review',
            'position' => 0,
            'author' => 'Anna',
            'rating' => 5,
            'text' => 'Still useful',
            'published_at' => new DateTimeImmutable('2026-05-10 12:00:00'),
        ]);

        $source = new class implements OrganizationDataSource
        {
            public function fetch(string $url): OrganizationData
            {
                throw new RuntimeException('Страница организации недоступна.');
            }
        };

        try {
            (new OrganizationSyncService($source))->sync($organization);
            $this->fail('Sync exception was not thrown.');
        } catch (OrganizationSyncException $exception) {
            $this->assertSame('Страница организации недоступна.', $exception->getMessage());
        }

        $organization->refresh();

        $this->assertSame('failed', $organization->sync_status);
        $this->assertSame('Страница организации недоступна.', $organization->sync_error);
        $this->assertSame('Cached company', $organization->name);
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'cached-review',
        ]);
    }
}
