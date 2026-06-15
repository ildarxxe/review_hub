<?php

namespace Tests\Feature;

use App\Jobs\SyncOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_user_can_save_yandex_maps_organization_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/organization', [
            'url' => 'https://yandex.ru/maps/org/yandeks/1124715036/reviews/',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath(
                'organization.source_url',
                'https://yandex.ru/maps/org/yandeks/1124715036/reviews',
            )
            ->assertJsonPath('organization.sync_status', 'pending');

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'source_url' => 'https://yandex.ru/maps/org/yandeks/1124715036/reviews',
        ]);
        Queue::assertPushed(
            SyncOrganization::class,
            fn (SyncOrganization $job) => $job->organizationId === $user->organization->id
                && $job->sourceUrl === 'https://yandex.ru/maps/org/yandeks/1124715036/reviews',
        );
    }

    public function test_regional_yandex_maps_url_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/organization', [
            'url' => 'https://yandex.ru/maps/213/moscow/org/example/123/',
        ])->assertAccepted();
    }

    public function test_shared_map_url_with_poi_organization_is_accepted(): void
    {
        $user = User::factory()->create();
        $url = 'https://yandex.kz/maps/10295/kostanai/?ll=63.616495%2C53.217643'
            .'&mode=poi&poi%5Bpoint%5D=63.569732%2C53.219686'
            .'&poi%5Buri%5D=ymapsbm1%3A%2F%2Forg%3Foid%3D162508758578'
            .'&utm_source=share&z=14';

        $this->actingAs($user)
            ->putJson('/api/organization', ['url' => $url])
            ->assertAccepted()
            ->assertJsonPath('organization.source_url', $url);

        Queue::assertPushed(
            SyncOrganization::class,
            fn (SyncOrganization $job) => $job->sourceUrl === $url,
        );
    }

    public function test_saving_a_new_url_resets_previous_organization_data(): void
    {
        $user = User::factory()->create();
        $user->organization()->create([
            'source_url' => 'https://yandex.ru/maps/org/first/100',
            'name' => 'First company',
            'rating' => 4.8,
            'ratings_count' => 100,
            'reviews_count' => 50,
            'sync_status' => 'ready',
            'synced_at' => now(),
        ]);

        $this->actingAs($user)->putJson('/api/organization', [
            'url' => 'https://yandex.com/maps/org/second/200',
        ])->assertAccepted();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'source_url' => 'https://yandex.com/maps/org/second/200',
            'name' => null,
            'rating' => null,
            'sync_status' => 'pending',
        ]);
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_organization_url_is_rejected(string $url): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/organization', ['url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidUrls(): array
    {
        return [
            'another website' => ['https://example.com/maps/org/company/123'],
            'lookalike domain' => ['https://yandex.ru.example.com/maps/org/company/123'],
            'maps home page' => ['https://yandex.ru/maps/'],
            'share url without oid' => [
                'https://yandex.kz/maps/10295/kostanai/?mode=poi'
                .'&poi%5Buri%5D=ymapsbm1%3A%2F%2Forg',
            ],
            'custom port' => ['https://yandex.ru:8080/maps/org/company/123'],
            'javascript scheme' => ['javascript:alert(1)'],
        ];
    }

    public function test_user_can_read_own_organization(): void
    {
        $user = User::factory()->create();
        $organization = $user->organization()->create([
            'source_url' => 'https://yandex.kz/maps/org/company/123',
        ]);

        $this->actingAs($user)
            ->getJson('/api/organization')
            ->assertOk()
            ->assertJsonPath('organization.id', $organization->id);
    }

    public function test_guest_cannot_read_or_update_organization(): void
    {
        $this->getJson('/api/organization')->assertUnauthorized();

        $this->putJson('/api/organization', [
            'url' => 'https://yandex.ru/maps/org/company/123',
        ])->assertUnauthorized();
    }
}
