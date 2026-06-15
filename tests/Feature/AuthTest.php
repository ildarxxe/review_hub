<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'login' => 'admin',
            'password' => '123456',
        ]);

        $response = $this->withHeader('Origin', config('app.url'))
            ->postJson('/api/login', [
                'login' => 'admin',
                'password' => '123456',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.login', 'admin')
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'login' => 'admin',
            'password' => '123456',
        ]);

        $this->postJson('/api/login', [
            'login' => 'admin',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');

        $this->assertGuest();
    }

    public function test_guest_cannot_access_current_user(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        User::factory()->create([
            'login' => 'admin',
            'password' => '123456',
        ]);

        $this->withHeader('Origin', config('app.url'))
            ->postJson('/api/login', [
                'login' => 'admin',
                'password' => '123456',
            ])
            ->assertOk();

        $this->withHeader('Origin', config('app.url'))
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Вы вышли из системы.');

        Auth::forgetGuards();

        $this->withHeader('Origin', config('app.url'))
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
