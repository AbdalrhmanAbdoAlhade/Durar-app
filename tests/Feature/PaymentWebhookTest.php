<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    protected function makeOrderWithPayment(): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-100',
            'user_id' => User::factory()->create()->id,
            'type' => 'product',
            'subtotal' => 500,
            'total' => 500,
        ]);

        $order->payments()->create([
            'gateway' => 'edfapay',
            'reference' => $order->order_number,
            'amount' => 500,
            'currency' => 'SAR',
            'status' => 'pending',
        ]);

        return $order;
    }

    public function test_successful_status_marks_order_paid(): void
    {
        $order = $this->makeOrderWithPayment();

        Http::fake(['*/payment/status' => Http::response(['status' => 'SUCCESS', 'trans_id' => 'TX-1'])]);

        $this->postJson('/api/payments/edfapay/webhook', ['order_id' => 'ORD-TEST-100'])->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'success', 'transaction_id' => 'TX-1']);
    }

    public function test_declined_status_marks_payment_failed(): void
    {
        $order = $this->makeOrderWithPayment();

        Http::fake(['*/payment/status' => Http::response(['status' => 'DECLINED'])]);

        $this->postJson('/api/payments/edfapay/webhook', ['order_id' => 'ORD-TEST-100'])->assertOk();

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
    }

    public function test_webhook_does_not_trust_payload_status(): void
    {
        $order = $this->makeOrderWithPayment();

        // Caller claims success, but EdfaPay itself says DECLINED.
        Http::fake(['*/payment/status' => Http::response(['status' => 'DECLINED'])]);

        $this->postJson('/api/payments/edfapay/webhook', ['order_id' => 'ORD-TEST-100', 'status' => 'SUCCESS'])->assertOk();

        $this->assertNotSame('paid', $order->fresh()->payment_status);
    }

    public function test_unknown_order_returns_404(): void
    {
        $this->postJson('/api/payments/edfapay/webhook', ['order_id' => 'NOPE'])->assertStatus(404);
    }

    public function test_gateway_verification_failure_returns_502_and_changes_nothing(): void
    {
        $order = $this->makeOrderWithPayment();

        Http::fake(['*/payment/status' => Http::response('error', 500)]);

        $this->postJson('/api/payments/edfapay/webhook', ['order_id' => 'ORD-TEST-100'])->assertStatus(502);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }
}
