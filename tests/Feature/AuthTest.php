<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'phone' => '01000000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'ahmed@example.com']);
    }

    public function test_register_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])->assertStatus(422);
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson('/api/auth/register', [])->assertStatus(422);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'password123']);

        $this->postJson('/api/auth/login', [
            'email' => 'ahmed@example.com',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'password123']);

        $this->postJson('/api/auth/login', [
            'email' => 'ahmed@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/auth/login', [])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, $user->tokens()->count());
    }
}
