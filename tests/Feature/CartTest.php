<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'discount_percentage' => 0,
            'quantity' => 10,
            'is_active' => true,
        ]);
    }

    public function test_guest_can_get_empty_cart(): void
    {
        $response = $this->getJson('/api/cart');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_guest_can_add_to_cart(): void
    {
        $response = $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    public function test_add_to_cart_validates_product_and_quantity(): void
    {
        $this->postJson('/api/cart/items', [])->assertStatus(422);

        $this->postJson('/api/cart/items', [
            'product_id' => 999999,
            'quantity' => 1,
        ])->assertStatus(422);

        $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 0,
        ])->assertStatus(422);
    }

    public function test_cannot_add_more_than_stock(): void
    {
        $response = $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_adding_to_cart_snapshots_discounted_price(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'discount_percentage' => 20, 'quantity' => 5]);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 1600);
    }

    public function test_user_can_update_cart_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $item = CartItem::first();
        $this->assertNotNull($item);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/cart/items/{$item->id}", [
            'quantity' => 3,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 3,
        ]);
    }

    public function test_update_cart_item_validates_quantity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $item = CartItem::first();
        $this->assertNotNull($item);

        $this->actingAs($user, 'sanctum')->putJson("/api/cart/items/{$item->id}", [])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->putJson("/api/cart/items/{$item->id}", ['quantity' => 100])->assertStatus(422);
    }

    public function test_user_can_remove_cart_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $item = CartItem::first();
        $this->assertNotNull($item);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/cart/items/{$item->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_user_can_clear_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/cart');

        $response->assertOk();
        $this->assertEquals(0, CartItem::count());
    }

    public function test_authenticated_user_cart(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
    }

    public function test_authenticated_user_has_persistent_cart(): void
    {
        $user = User::factory()->create();
        Cart::create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
