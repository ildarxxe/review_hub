<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviews_are_paginated_by_fifty_in_source_order(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization($user);
        $this->insertReviews($organization, 55);

        $firstPage = $this->actingAs($user)->getJson('/api/reviews');

        $firstPage
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('data.0.author', 'Author 0')
            ->assertJsonPath('data.49.author', 'Author 49')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 55)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonMissingPath('data.0.external_id')
            ->assertJsonMissingPath('data.0.position')
            ->assertJsonMissingPath('data.0.created_at');

        $this->actingAs($user)
            ->getJson('/api/reviews?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.author', 'Author 50')
            ->assertJsonPath('data.4.author', 'Author 54')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_reviews_from_another_user_are_not_returned(): void
    {
        $user = User::factory()->create();
        $this->organization($user);

        $anotherUser = User::factory()->create();
        $anotherOrganization = $this->organization($anotherUser);
        $this->insertReviews($anotherOrganization, 1);

        $this->actingAs($user)
            ->getJson('/api/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_user_without_organization_receives_empty_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_guest_cannot_read_reviews(): void
    {
        $this->getJson('/api/reviews')->assertUnauthorized();
    }

    private function organization(User $user): Organization
    {
        return $user->organization()->create([
            'source_url' => 'https://yandex.ru/maps/org/company/'.$user->id,
            'sync_status' => 'ready',
        ]);
    }

    private function insertReviews(Organization $organization, int $count): void
    {
        $now = now();
        $reviews = [];

        for ($position = 0; $position < $count; $position++) {
            $reviews[] = [
                'organization_id' => $organization->id,
                'external_id' => 'review-'.$organization->id.'-'.$position,
                'position' => $position,
                'author' => 'Author '.$position,
                'rating' => ($position % 5) + 1,
                'text' => $position % 3 === 0 ? null : 'Review text '.$position,
                'published_at' => $now->copy()->subDays($position % 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('reviews')->insert($reviews);
    }
}
