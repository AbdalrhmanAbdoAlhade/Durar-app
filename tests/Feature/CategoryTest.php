<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_public_can_list_categories(): void
    {
        Category::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_public_can_show_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id);
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/categories', [
            'name_ar'   => 'أحجار',
            'name_en'   => 'Stones',
            'slug'      => 'stones',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name_en', 'Stones');

        $this->assertDatabaseHas('categories', ['name_en' => 'Stones', 'slug' => 'stones']);
    }

    public function test_customer_cannot_create_category(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/categories', [
            'name_ar' => 'أحجار',
            'name_en' => 'Stones',
            'slug'    => 'stones-2',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $category = Category::factory()->create();

        $response = $this->putJson("/api/admin/categories/{$category->id}", [
            'name_ar' => 'محدث',
            'name_en' => 'Updated',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name_en', 'Updated');
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
