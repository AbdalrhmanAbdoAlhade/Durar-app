<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
            'category_id'         => $category->id,
            'price'               => 1000,
            'discount_percentage' => 0,
            'quantity'            => 10,
            'is_active'           => true,
        ]);
    }

    public function test_guest_can_get_empty_cart(): void
    {
        $response = $this->withSession([])->getJson('/api/cart');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_guest_can_add_to_cart(): void
    {
        $response = $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity'   => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'quantity'   => 2,
        ]);
    }

    public function test_cannot_add_more_than_stock(): void
    {
        $response = $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity'   => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_can_update_cart_item(): void
    {
        $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $item = CartItem::first();
        $this->assertNotNull($item);

        $response = $this->putJson("/api/cart/items/{$item->id}", [
            'quantity' => 3,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cart_items', [
            'id'       => $item->id,
            'quantity' => 3,
        ]);
    }

    public function test_guest_can_remove_cart_item(): void
    {
        $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $item = CartItem::first();

        $response = $this->deleteJson("/api/cart/items/{$item->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_guest_can_clear_cart(): void
    {
        $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $response = $this->deleteJson('/api/cart');

        $response->assertOk();
        $this->assertEquals(0, CartItem::count());
    }

    public function test_authenticated_user_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart/items', [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);
    }
}
