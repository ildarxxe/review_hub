<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_yandex_maps_organization_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/organization', [
            'url' => 'https://yandex.ru/maps/org/yandeks/1124715036/reviews/',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'organization.source_url',
                'https://yandex.ru/maps/org/yandeks/1124715036/reviews',
            )
            ->assertJsonPath('organization.sync_status', 'pending');

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'source_url' => 'https://yandex.ru/maps/org/yandeks/1124715036/reviews',
        ]);
    }

    public function test_regional_yandex_maps_url_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/organization', [
            'url' => 'https://yandex.ru/maps/213/moscow/org/example/123/',
        ])->assertOk();
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
        ])->assertOk();

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
