<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAdmin;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    public function test_guest_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/admin/categories')->assertStatus(401);
    }

    public function test_non_admin_user_gets_forbidden_on_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/categories')
            ->assertStatus(403);
    }

    public function test_admin_can_create_category(): void
    {
        Storage::fake('public');

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->post('/api/admin/categories', [
                'name_ar' => 'خواتم',
                'name_en' => 'Rings',
                'image' => UploadedFile::fake()->image('cat.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'rings');

        $this->assertDatabaseHas('categories', ['name_en' => 'Rings']);
    }

    public function test_category_slug_stays_unique_for_same_english_name(): void
    {
        $admin = $this->createAdmin();

        foreach ([1, 2] as $_) {
            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/admin/categories', ['name_ar' => 'خواتم', 'name_en' => 'Rings'])
                ->assertStatus(201);
        }

        $this->assertSame(
            ['rings', 'rings-1'],
            Category::orderBy('id')->pluck('slug')->all()
        );
    }

    public function test_admin_can_create_product_with_cover_and_gallery(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->post('/api/admin/products', [
                'category_id' => $category->id,
                'name_ar' => 'خاتم ذهب',
                'name_en' => 'Gold Ring',
                'price' => 5000,
                'discount_percentage' => 10,
                'quantity' => 20,
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
                'gallery' => [
                    UploadedFile::fake()->image('g1.jpg'),
                    UploadedFile::fake()->image('g2.jpg'),
                ],
            ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.final_price', 4500);

        $product = Product::first();
        $this->assertCount(2, $product->images);
    }

    public function test_product_discount_cannot_exceed_100_percent(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson('/api/admin/products', [
                'category_id' => $category->id,
                'name_ar' => 'x',
                'name_en' => 'x',
                'price' => 100,
                'discount_percentage' => 150,
                'quantity' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_public_product_list_hides_inactive_products(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/products')->assertOk();

        $items = $response->json('data.data') ?? $response->json('data');
        $this->assertCount(1, $items);
    }
}
