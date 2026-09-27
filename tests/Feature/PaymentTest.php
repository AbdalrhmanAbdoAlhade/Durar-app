<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\EdfaPayService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(EdfaPayService::class, function ($mock) {
            $mock->shouldReceive('checkStatus')->andReturn([
                'status'  => 'SUCCESS',
                'trans_id'=> 'TXN-TEST-001',
            ]);
            $mock->shouldReceive('initiate')->andReturn('https://pay.example.com/redirect');
        });

        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });
    }

    public function test_user_can_view_order_payment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status'  => 'pending',
            'total'   => 1500,
        ]);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => 'edfapay',
            'transaction_id' => null,
            'reference'      => $order->order_number,
            'amount'         => 1500,
            'currency'       => 'SAR',
            'status'         => 'pending',
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/payment");

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_cannot_view_other_user_payment(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $other->id,
            'status'  => 'pending',
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/payment");

        $response->assertNotFound();
    }

    public function test_webhook_processes_known_order(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id'        => $user->id,
            'status'         => 'pending',
            'payment_status' => 'unpaid',
            'total'          => 1500,
        ]);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => 'edfapay',
            'transaction_id' => null,
            'reference'      => $order->order_number,
            'amount'         => 1500,
            'currency'       => 'SAR',
            'status'         => 'pending',
        ]);

        $response = $this->postJson('/api/payments/edfapay/webhook', [
            'order_id' => $order->order_number,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_webhook_unknown_order_returns_404(): void
    {
        $response = $this->postJson('/api/payments/edfapay/webhook', [
            'order_id' => 'ORD-DOES-NOT-EXIST',
        ]);

        $response->assertNotFound();
    }

    public function test_webhook_missing_order_id_returns_422(): void
    {
        $response = $this->postJson('/api/payments/edfapay/webhook', []);

        $response->assertStatus(422);
    }
}
