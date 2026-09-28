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

class ProductTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    protected function items($response): array
    {
        return $response->json('data.data') ?? $response->json('data');
    }

    public function test_public_can_list_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(5)->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_public_product_list_hides_inactive_products(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/products')->assertOk();

        $this->assertCount(1, $this->items($response));
    }

    public function test_public_can_filter_products_by_category(): void
    {
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();

        Product::factory()->count(3)->create(['category_id' => $cat1->id, 'is_active' => true]);
        Product::factory()->count(2)->create(['category_id' => $cat2->id, 'is_active' => true]);

        $response = $this->getJson("/api/products?category_id={$cat1->id}");

        $response->assertOk();
        $this->assertCount(3, $this->items($response));
    }

    public function test_public_can_show_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_guest_cannot_create_product(): void
    {
        $this->postJson('/api/admin/products', [])->assertStatus(401);
    }

    public function test_admin_can_create_product(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $response = $this->actingAs($this->createAdmin(), 'sanctum')
            ->post('/api/admin/products', [
                'category_id' => $category->id,
                'name_ar' => 'ياقوت',
                'name_en' => 'Ruby',
                'description_ar' => 'حجر ياقوت',
                'description_en' => 'Ruby stone',
                'price' => 1500,
                'discount_percentage' => 10,
                'quantity' => 5,
                'is_active' => true,
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['name_en' => 'Ruby']);
    }

    public function test_create_product_validates_required_fields(): void
    {
        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson('/api/admin/products', [])
            ->assertStatus(422);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = $this->createAdmin();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/products/{$product->id}", [
            'price' => 1200,
            'discount_percentage' => 20,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 1200,
            'discount_percentage' => 20,
        ]);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = $this->createAdmin();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/products/{$product->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_customer_cannot_create_product(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'name_ar' => 'ياقوت',
            'name_en' => 'Ruby',
            'slug' => 'ruby-2',
            'price' => 1000,
            'quantity' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_customer_cannot_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertForbidden();
    }
}
