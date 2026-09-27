<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'phone'                 => '0501234567',
            'password'              => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['user', 'token'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '0501234567',
        ]);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email'    => 'login@example.com',
            'password' => Hash::make('Password@123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['user', 'token'],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'wrong@example.com',
            'password' => Hash::make('Password@123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'wrong@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_me(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertOk();
    }

    public function test_unauthenticated_cannot_access_me(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
