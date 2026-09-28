<?php

namespace Tests\Feature;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_fcm_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/fcm-tokens', [
            'token' => 'fcm-token-abc123',
            'device_type' => 'android',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'device_type' => 'android',
        ]);
    }

    public function test_registering_same_token_updates_existing(): void
    {
        $user = User::factory()->create();

        FcmToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-abc123',
            'device_type' => 'ios',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/fcm-tokens', [
            'token' => 'fcm-token-abc123',
            'device_type' => 'android',
        ]);

        $response->assertOk();
        $this->assertEquals(1, FcmToken::where('token', 'fcm-token-abc123')->count());
        $this->assertEquals('android', FcmToken::first()->device_type);
    }

    public function test_register_token_validates_token_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fcm-tokens', [])
            ->assertStatus(422);
    }

    public function test_register_token_validates_device_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/fcm-tokens', ['token' => 'x', 'device_type' => 'blackberry'])
            ->assertStatus(422);
    }

    public function test_user_can_delete_token(): void
    {
        $user = User::factory()->create();

        FcmToken::create([
            'user_id' => $user->id,
            'token' => 'fcm-to-delete',
            'device_type' => 'android',
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/fcm-tokens', [
            'token' => 'fcm-to-delete',
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'fcm-to-delete']);
    }

    public function test_delete_token_validates_token_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/fcm-tokens', [])
            ->assertStatus(422);
    }

    public function test_user_cannot_delete_another_users_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        FcmToken::create(['user_id' => $other->id, 'token' => 'other-token', 'device_type' => 'web']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/fcm-tokens', ['token' => 'other-token'])
            ->assertOk();

        // Scoped to the current user, so the other user's token stays.
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'other-token', 'user_id' => $other->id]);
    }

    public function test_unauthenticated_cannot_register_token(): void
    {
        $response = $this->postJson('/api/fcm-tokens', [
            'token' => 'some-token',
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_delete_token(): void
    {
        $this->deleteJson('/api/fcm-tokens', ['token' => 'some-token'])->assertUnauthorized();
    }
}
