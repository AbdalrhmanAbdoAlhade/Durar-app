<?php

return [
    // Sandbox: https://dev-api.edfapay.com | Production: https://api.edfapay.com
    'base_url' => env('EDFAPAY_BASE_URL', 'https://dev-api.edfapay.com'),

    'merchant_id' => env('EDFAPAY_MERCHANT_ID'),
    'merchant_password' => env('EDFAPAY_MERCHANT_PASSWORD'),

    'currency' => env('EDFAPAY_CURRENCY', 'SAR'),

    // Where EdfaPay redirects the customer's browser back to after paying
    'return_url' => env('EDFAPAY_RETURN_URL', env('APP_URL').'/payment/return'),

    // Server-to-server callback (must be publicly reachable) — wired to PaymentController::edfapayWebhook
    'callback_url' => env('EDFAPAY_CALLBACK_URL', env('APP_URL').'/api/payments/edfapay/webhook'),
];
