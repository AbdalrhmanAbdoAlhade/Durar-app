<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NOTE: EdfaPay's public docs describe more than one hash/signature formula across
 * API versions (S2S vs hosted Checkout). Before going live, confirm the exact
 * signing formula and required fields from your EdfaPay merchant dashboard /
 * Postman collection, and adjust buildHash() accordingly.
 *
 * As a safety net, we don't trust the webhook payload's own signature blindly —
 * on every callback we re-query EdfaPay's /payment/status endpoint with our own
 * merchant credentials and only trust that response.
 */
class EdfaPayService
{
    protected string $baseUrl;
    protected string $merchantId;
    protected string $merchantPassword;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('edfapay.base_url'), '/');
        $this->merchantId = (string) config('edfapay.merchant_id');
        $this->merchantPassword = (string) config('edfapay.merchant_password');
    }

    /**
     * Initiate a hosted checkout session for an order and return the redirect URL
     * the customer's browser should be sent to.
     */
    public function initiate(Order $order, array $payer): ?string
    {
        $payload = [
            'merchant_id' => $this->merchantId,
            'order_id' => $order->order_number,
            'order_amount' => number_format((float) $order->total, 2, '.', ''),
            'order_currency' => config('edfapay.currency'),
            'order_description' => "Order #{$order->order_number}",
            'payer_first_name' => $payer['first_name'] ?? $payer['name'] ?? 'Customer',
            'payer_last_name' => $payer['last_name'] ?? '-',
            'payer_email' => $payer['email'] ?? null,
            'payer_phone' => $payer['phone'] ?? null,
            'return_url' => config('edfapay.return_url'),
            'callback_url' => config('edfapay.callback_url'),
        ];
        $payload['hash'] = $this->buildHash($order->order_number);

        $response = Http::asJson()->post("{$this->baseUrl}/payment/initiate", $payload);

        if (! $response->successful()) {
            Log::error('EdfaPay initiate failed', ['order' => $order->order_number, 'response' => $response->body()]);

            return null;
        }

        return $response->json('redirect_url');
    }

    /**
     * Re-check a transaction's real status directly with EdfaPay (don't trust
     * the webhook payload alone).
     */
    public function checkStatus(string $orderNumber): ?array
    {
        $payload = [
            'order_id' => $orderNumber,
            'merchant_id' => $this->merchantId,
            'hash' => $this->buildHash($orderNumber),
        ];

        $response = Http::asJson()->post("{$this->baseUrl}/payment/status", $payload);

        if (! $response->successful()) {
            Log::error('EdfaPay status check failed', ['order' => $orderNumber, 'response' => $response->body()]);

            return null;
        }

        return $response->json();
    }

    /**
     * Per EdfaPay's Checkout docs: SHA1 of an MD5-encoded string (uppercased),
     * built from the order id and merchant password. CONFIRM this against your
     * dashboard before production use — see class docblock.
     */
    protected function buildHash(string $orderId): string
    {
        return strtoupper(sha1(md5($orderId.$this->merchantPassword)));
    }
}
