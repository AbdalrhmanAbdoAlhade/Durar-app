<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreatesAdmin;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/payment/initiate' => Http::response(['redirect_url' => 'https://pay.test/redirect']),
        ]);
    }

    protected function address(): array
    {
        return ['name' => 'Ahmed', 'phone' => '01000000000', 'city' => 'Cairo', 'address_line' => '123 Main St'];
    }

    protected function items($response): array
    {
        return $response->json('data.data') ?? $response->json('data');
    }

    public function test_guest_cannot_access_admin_coupons(): void
    {
        $this->getJson('/api/admin/coupons')->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_coupons(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/coupons')
            ->assertStatus(403);
    }

    public function test_admin_can_list_coupons(): void
    {
        Coupon::factory()->count(2)->create();

        $response = $this->actingAs($this->createAdmin(), 'sanctum')
            ->getJson('/api/admin/coupons')
            ->assertOk();

        $this->assertCount(2, $this->items($response));
    }

    public function test_admin_can_create_coupon(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson('/api/admin/coupons', [
                'code' => 'SAVE20',
                'type' => 'percentage',
                'value' => 20,
                'is_active' => true,
                'category_ids' => [$category->id],
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('coupons', ['code' => 'SAVE20']);
    }

    public function test_admin_create_coupon_validates_fields(): void
    {
        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson('/api/admin/coupons', [])
            ->assertStatus(422);
    }

    public function test_customer_cannot_create_coupon(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/admin/coupons', ['code' => 'X', 'type' => 'fixed', 'value' => 5])
            ->assertStatus(403);
    }

    public function test_admin_can_update_coupon(): void
    {
        $coupon = Coupon::factory()->create(['value' => 10]);

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->putJson("/api/admin/coupons/{$coupon->id}", ['value' => 25])
            ->assertOk();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'value' => 25]);
    }

    public function test_admin_can_delete_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->deleteJson("/api/admin/coupons/{$coupon->id}")
            ->assertOk();

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_category_scoped_coupon_discounts_only_matching_items(): void
    {
        $user = User::factory()->create();
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        $a = Product::factory()->create(['category_id' => $catA->id, 'price' => 1000, 'quantity' => 10]);
        $b = Product::factory()->create(['category_id' => $catB->id, 'price' => 500, 'quantity' => 10]);

        $coupon = Coupon::factory()->create(['code' => 'CATA10', 'type' => 'percentage', 'value' => 10]);
        $coupon->categories()->sync([$catA->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['product_id' => $a->id, 'quantity' => 2]);
        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['product_id' => $b->id, 'quantity' => 1]);

        // subtotal 2500, discount = 10% of category A items only (2000) = 200, shipping 30 => 2330
        $this->actingAs($user, 'sanctum')
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
        $user = User::factory()->create();
        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $catB->id, 'quantity' => 10]);

        $coupon = Coupon::factory()->create(['code' => 'CATA10']);
        $coupon->categories()->sync([$catA->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders/checkout', [
                'coupon_code' => 'CATA10',
                'shipping_address' => $this->address(),
            ])
            ->assertStatus(422);

        $this->assertSame(0, \App\Models\Order::count());
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $cat = Category::factory()->create();
        Product::factory()->create(['category_id' => $cat->id]);

        $coupon = Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $coupon->categories()->sync([$cat->id]);

        $this->postJson('/api/coupons/check', ['code' => 'OLD', 'category_ids' => [$cat->id]])
            ->assertStatus(422);
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        $cat = Category::factory()->create();
        Product::factory()->create(['category_id' => $cat->id]);

        $coupon = Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
        $coupon->categories()->sync([$cat->id]);

        $this->postJson('/api/coupons/check', ['code' => 'OFF', 'category_ids' => [$cat->id]])
            ->assertStatus(422);
    }
}
