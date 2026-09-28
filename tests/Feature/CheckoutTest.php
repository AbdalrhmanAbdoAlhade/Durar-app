<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->mock(NotificationService::class)->shouldIgnoreMissing();

        Http::fake([
            '*/payment/initiate' => Http::response(['redirect_url' => 'https://pay.test/redirect']),
        ]);
    }

    protected function address(): array
    {
        return [
            'name' => 'Ahmed',
            'phone' => '01000000000',
            'city' => 'Cairo',
            'address_line' => '123 Main St',
        ];
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->postJson('/api/orders/checkout', [
            'shipping_address' => $this->address(),
        ])->assertStatus(401);
    }

    public function test_checkout_validates_shipping_address(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'quantity' => 10]);
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/checkout', [])
            ->assertStatus(422);
    }

    public function test_adding_to_cart_snapshots_discounted_price(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'discount_percentage' => 20, 'quantity' => 5]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 1600);
    }

    public function test_cannot_add_more_than_available_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 2]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 3])
            ->assertStatus(422);
    }

    public function test_checkout_creates_order_decrements_stock_and_clears_cart(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'quantity' => 10]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/checkout', [
                'shipping_fee' => 30,
                'shipping_address' => $this->address(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.payment_redirect_url', 'https://pay.test/redirect')
            ->assertJsonPath('data.order.total', 2030);

        $order = Order::first();
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(8, $product->fresh()->quantity);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending', 'gateway' => 'edfapay']);
        $this->assertSame(0, $this->user->cart->items()->count());
    }

    public function test_checkout_with_empty_cart_fails(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/checkout', ['shipping_address' => $this->address()])
            ->assertStatus(422);
    }

    public function test_category_scoped_coupon_discounts_only_matching_items(): void
    {
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        $a = Product::factory()->create(['category_id' => $catA->id, 'price' => 1000, 'quantity' => 10]);
        $b = Product::factory()->create(['category_id' => $catB->id, 'price' => 500, 'quantity' => 10]);

        $coupon = Coupon::factory()->create(['code' => 'CATA10', 'type' => 'percentage', 'value' => 10]);
        $coupon->categories()->sync([$catA->id]);

        $this->actingAs($this->user, 'sanctum')->postJson('/api/cart/items', ['product_id' => $a->id, 'quantity' => 2]);
        $this->actingAs($this->user, 'sanctum')->postJson('/api/cart/items', ['product_id' => $b->id, 'quantity' => 1]);

        // subtotal 2500, discount = 10% of category A items only (2000) = 200, shipping 30 => 2330
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/checkout', [
                'coupon_code' => 'CATA10',
                'shipping_fee' => 30,
                'shipping_address' => $this->address(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.order.discount_amount', 200)
            ->assertJsonPath('data.order.total', 2330);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_coupon_not_covering_cart_categories_is_rejected_without_side_effects(): void
    {
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $catB->id, 'quantity' => 10]);

        $coupon = Coupon::factory()->create(['code' => 'CATA10']);
        $coupon->categories()->sync([$catA->id]);

        $this->actingAs($this->user, 'sanctum')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/orders/checkout', [
                'coupon_code' => 'CATA10',
                'shipping_address' => $this->address(),
            ])
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $cat = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $cat->id]);

        $coupon = Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $coupon->categories()->sync([$cat->id]);

        $this->postJson('/api/coupons/check', ['code' => 'OLD', 'category_ids' => [$cat->id]])
            ->assertStatus(422);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-1',
            'user_id' => User::factory()->create()->id,
            'type' => 'product',
            'total' => 100,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertStatus(404);
    }
}
