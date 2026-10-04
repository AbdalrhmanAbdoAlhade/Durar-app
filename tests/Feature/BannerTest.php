<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAdmin;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    protected function items($response): array
    {
        return $response->json('data.data') ?? $response->json('data');
    }

    public function test_public_can_list_banners(): void
    {
        Banner::create([
            'type' => 'normal', 'title_ar' => 'عرض', 'title_en' => 'Offer',
            'image' => 'banners/test.jpg', 'is_active' => true,
        ]);

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_public_list_hides_inactive_banners(): void
    {
        Banner::create([
            'type' => 'normal', 'title_ar' => 'a', 'title_en' => 'a',
            'image' => 'banners/a.jpg', 'is_active' => true,
        ]);
        Banner::create([
            'type' => 'normal', 'title_ar' => 'b', 'title_en' => 'b',
            'image' => 'banners/b.jpg', 'is_active' => false,
        ]);

        $response = $this->getJson('/api/banners')->assertOk();

        $this->assertCount(1, $this->items($response));
    }

    public function test_guest_cannot_access_admin_banners(): void
    {
        $this->getJson('/api/admin/banners')->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_banners(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/banners')
            ->assertStatus(403);
    }

    public function test_admin_can_list_banners(): void
    {
        Banner::create([
            'type' => 'offer', 'title_ar' => 'a', 'title_en' => 'a',
            'image' => 'banners/a.jpg', 'is_active' => false,
        ]);

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->getJson('/api/admin/banners')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_create_banner(): void
    {
        Storage::fake('public');

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->post('/api/admin/banners', [
                'type' => 'normal',
                'title_ar' => 'عرض جديد',
                'title_en' => 'New offer',
                'image' => UploadedFile::fake()->image('banner.jpg'),
                'is_active' => true,
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $this->assertDatabaseHas('banners', ['title_en' => 'New offer']);
    }

    public function test_admin_create_banner_validates_fields(): void
    {
        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson('/api/admin/banners', [])
            ->assertStatus(422);
    }

    public function test_customer_cannot_create_banner(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->post('/api/admin/banners', [
                'type' => 'normal',
                'image' => UploadedFile::fake()->image('banner.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(403);
    }

    public function test_admin_can_update_banner(): void
    {
        $banner = Banner::create([
            'type' => 'normal', 'title_ar' => 'a', 'title_en' => 'a',
            'image' => 'banners/a.jpg', 'is_active' => true,
        ]);

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->putJson("/api/admin/banners/{$banner->id}", ['title_en' => 'Updated'])
            ->assertOk();

        $this->assertDatabaseHas('banners', ['id' => $banner->id, 'title_en' => 'Updated']);
    }

    public function test_admin_can_delete_banner(): void
    {
        $banner = Banner::create([
            'type' => 'normal', 'title_ar' => 'a', 'title_en' => 'a',
            'image' => 'banners/a.jpg', 'is_active' => true,
        ]);

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->deleteJson("/api/admin/banners/{$banner->id}")
            ->assertOk();

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
    }

    public function test_customer_cannot_delete_banner(): void
    {
        $banner = Banner::create([
            'type' => 'normal', 'title_ar' => 'a', 'title_en' => 'a',
            'image' => 'banners/a.jpg', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/banners/{$banner->id}")
            ->assertStatus(403);
    }
}
