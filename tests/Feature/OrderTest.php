<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\EdfaPayService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesAdmin;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    protected Product $product;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Avoid real EdfaPay / Firebase during tests
        $this->mock(EdfaPayService::class, function ($mock) {
            $mock->shouldReceive('initiate')->andReturn('https://pay.example.com/redirect');
            $mock->shouldReceive('checkStatus')->andReturn(['status' => 'SUCCESS']);
        });

        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        $category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'discount_percentage' => 0,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();
    }

    protected function address(): array
    {
        return [
            'name' => 'أحمد محمد',
            'phone' => '0501234567',
            'city' => 'الرياض',
            'address_line' => 'الرياض، حي النرجس',
        ];
    }

    protected function items($response): array
    {
        return $response->json('data.data') ?? $response->json('data');
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->postJson('/api/orders/checkout', [
            'shipping_address' => $this->address(),
        ])->assertStatus(401);
    }

    public function test_user_can_checkout_from_cart(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 1000,
        ]);

        $response = $this->postJson('/api/orders/checkout', [
            'shipping_address' => $this->address(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['order', 'payment_redirect_url'],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'type' => 'product',
        ]);
    }

    public function test_checkout_validates_shipping_address(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        $this->postJson('/api/orders/checkout', [])->assertStatus(422);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/orders/checkout', [
            'shipping_address' => $this->address(),
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_with_insufficient_stock_fails(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 1000,
        ]);
        $this->product->update(['quantity' => 2]);

        $this->postJson('/api/orders/checkout', [
            'shipping_address' => $this->address(),
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_winner_can_checkout_auction(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $auction = Auction::factory()->create([
            'status' => 'ended',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'winner_id' => $this->user->id,
            'current_price' => 2500,
        ]);

        $response = $this->postJson("/api/auctions/{$auction->id}/checkout", [
            'shipping_address' => $this->address(),
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['order', 'payment_redirect_url'],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'type' => 'auction',
            'auction_id' => $auction->id,
        ]);
    }

    public function test_non_winner_cannot_checkout_auction(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $other = User::factory()->create();
        $auction = Auction::factory()->create([
            'status' => 'ended',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'winner_id' => $other->id,
        ]);

        $response = $this->postJson("/api/auctions/{$auction->id}/checkout", [
            'shipping_address' => $this->address(),
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
    }

    public function test_user_can_list_own_orders(): void
    {
        $this->actingAs($this->user, 'sanctum');

        Order::factory()->count(3)->create(['user_id' => $this->user->id]);
        Order::factory()->create(); // other user

        $response = $this->getJson('/api/orders');

        $response->assertOk();
        $this->assertCount(3, $this->items($response));
    }

    public function test_user_can_show_own_order(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $order = Order::factory()->create();

        $this->getJson("/api/orders/{$order->id}")->assertStatus(404);
    }

    public function test_guest_cannot_access_admin_orders(): void
    {
        $this->getJson('/api/admin/orders')->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_orders(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertStatus(403);
    }

    public function test_admin_can_list_orders(): void
    {
        Order::factory()->count(2)->create();

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'sanctum');

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'type' => 'product',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_admin_update_status_validates_status(): void
    {
        $this->actingAs($this->createAdmin(), 'sanctum');

        $order = Order::factory()->create(['status' => 'pending']);

        $this->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'flying'])
            ->assertStatus(422);

        $this->putJson("/api/admin/orders/{$order->id}/status", [])
            ->assertStatus(422);
    }

    public function test_customer_cannot_update_order_status(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $this->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertStatus(403);
    }
}
