<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesAdmin;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    public function test_public_can_list_categories(): void
    {
        Category::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_public_can_show_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_guest_cannot_create_category(): void
    {
        $this->postJson('/api/admin/categories', [
            'name_ar' => 'أحجار',
            'name_en' => 'Stones',
        ])->assertStatus(401);
    }

    public function test_admin_can_create_category(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/categories', [
            'name_ar' => 'أحجار',
            'name_en' => 'Stones',
            'slug' => 'stones',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name_en', 'Stones');

        $this->assertDatabaseHas('categories', ['name_en' => 'Stones', 'slug' => 'stones']);
    }

    public function test_create_category_validates_required_fields(): void
    {
        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson('/api/admin/categories', [])
            ->assertStatus(422);
    }

    public function test_customer_cannot_create_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admin/categories', [
            'name_ar' => 'أحجار',
            'name_en' => 'Stones',
            'slug' => 'stones-2',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/categories/{$category->id}", [
            'name_ar' => 'محدث',
            'name_en' => 'Updated',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_en', 'Updated');
    }

    public function test_customer_cannot_update_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", ['name_en' => 'Hack'])
            ->assertForbidden();
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_customer_cannot_delete_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertForbidden();
    }
}
