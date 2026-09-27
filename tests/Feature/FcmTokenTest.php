<?php

namespace Tests\Feature;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_fcm_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/fcm-tokens', [
            'token'       => 'fcm-token-abc123',
            'device_type' => 'android',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id'     => $user->id,
            'token'       => 'fcm-token-abc123',
            'device_type' => 'android',
        ]);
    }

    public function test_registering_same_token_updates_existing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        FcmToken::create([
            'user_id'     => $user->id,
            'token'       => 'fcm-token-abc123',
            'device_type' => 'ios',
        ]);

        $response = $this->postJson('/api/fcm-tokens', [
            'token'       => 'fcm-token-abc123',
            'device_type' => 'android',
        ]);

        $response->assertOk();
        $this->assertEquals(1, FcmToken::where('token', 'fcm-token-abc123')->count());
        $this->assertEquals('android', FcmToken::first()->device_type);
    }

    public function test_user_can_delete_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        FcmToken::create([
            'user_id'     => $user->id,
            'token'       => 'fcm-to-delete',
            'device_type' => 'android',
        ]);

        $response = $this->deleteJson('/api/fcm-tokens', [
            'token' => 'fcm-to-delete',
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('fcm_tokens', ['token' => 'fcm-to-delete']);
    }

    public function test_unauthenticated_cannot_register_token(): void
    {
        $response = $this->postJson('/api/fcm-tokens', [
            'token' => 'some-token',
        ]);

        $response->assertUnauthorized();
    }
}
