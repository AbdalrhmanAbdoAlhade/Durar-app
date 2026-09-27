<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\EdfaPayService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

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
            'category_id'         => $category->id,
            'price'               => 1000,
            'discount_percentage' => 0,
            'quantity'            => 10,
            'is_active'           => true,
        ]);
        $this->user = User::factory()->create();
    }

    public function test_user_can_checkout_from_cart(): void
    {
        Sanctum::actingAs($this->user);

        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $this->product->id,
            'quantity'   => 2,
            'unit_price' => 1000,
        ]);

        $response = $this->postJson('/api/orders/checkout', [
            'shipping_address' => [
                'name'    => 'أحمد محمد',
                'phone'   => '0501234567',
                'address' => 'الرياض، حي النرجس',
                'city'    => 'الرياض',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['order', 'payment_redirect_url'],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'type'    => 'product',
        ]);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/orders/checkout', [
            'shipping_address' => [
                'name'    => 'أحمد',
                'phone'   => '0501234567',
                'address' => 'الرياض',
                'city'    => 'الرياض',
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_winner_can_checkout_auction(): void
    {
        Sanctum::actingAs($this->user);

        $auction = Auction::factory()->ended()->create([
            'winner_id'     => $this->user->id,
            'current_price' => 2500,
        ]);

        $response = $this->postJson("/api/auctions/{$auction->id}/checkout", [
            'shipping_address' => [
                'name'    => 'أحمد محمد',
                'phone'   => '0501234567',
                'address' => 'الرياض',
                'city'    => 'الرياض',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['order', 'payment_redirect_url'],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id'    => $this->user->id,
            'type'       => 'auction',
            'auction_id' => $auction->id,
        ]);
    }

    public function test_non_winner_cannot_checkout_auction(): void
    {
        Sanctum::actingAs($this->user);

        $other = User::factory()->create();
        $auction = Auction::factory()->ended()->create([
            'winner_id' => $other->id,
        ]);

        $response = $this->postJson("/api/auctions/{$auction->id}/checkout", [
            'shipping_address' => [
                'name'    => 'أحمد',
                'phone'   => '0501234567',
                'address' => 'الرياض',
                'city'    => 'الرياض',
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_list_own_orders(): void
    {
        Sanctum::actingAs($this->user);

        Order::factory()->count(3)->create(['user_id' => $this->user->id]);
        Order::factory()->create(); // other user

        $response = $this->getJson('/api/orders');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'pending',
            'type'    => 'product',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }
}
