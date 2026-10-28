<?php

return [

    /*
    | Platform default payment gateway driver: "thawani" (Thawani Pay hosted
    | checkout), "bankmuscat" (CCAvenue non-seamless) or "fake" (local page
    | with Simulate success / failure buttons, for development and automated
    | tests; refused when APP_ENV=production). A tenant can pick its own in
    | Admin → Tenants → payment gateway (settings payments.driver).
    */
    'driver' => env('PAYMENT_DRIVER', 'thawani'),

    // Lifetime of the signed payment page link returned as payment_url.
    'link_ttl_minutes' => (int) env('PAYMENT_LINK_TTL_MINUTES', 60),

    // Wallet pay-token (QR shown to vendor staff).
    'pay_token_ttl_seconds' => (int) env('PAY_TOKEN_TTL_SECONDS', 60),

    // Base URL of the public box office payment links (/pay/{token}, shown
    // as a QR on the gate and sent by SMS). Defaults to APP_URL; outside
    // production a default APP_URL of http://localhost falls back to the
    // host the gate device called (e.g. http://192.168.1.20:8002).
    'public_url' => env('PAY_LINK_BASE_URL'),

    // Max tickets per checkout.
    'max_tickets_per_order' => 10,

    /*
    | Thawani Pay (docs/payments_thawani.md). Keys are per tenant (encrypted,
    | Admin → Tenants) with config/services.php `thawani` as the fallback.
    */
    'thawani' => [
        'base_urls' => [
            'uat' => 'https://uatcheckout.thawani.om',
            'production' => 'https://checkout.thawani.om',
        ],
        // Product line shown on the Thawani page is cut to this length.
        'product_name_max' => 40,
        // Webhook timestamps older than this (seconds) are rejected (replay guard); 0 = off.
        'webhook_tolerance_seconds' => (int) env('THAWANI_WEBHOOK_TOLERANCE', 600),
    ],

    /*
    | Outbound gateway HTTP calls (App\Services\Payments\Gateway\GatewayHttp).
    | Retried with exponential backoff + jitter on network errors, 408, 425,
    | 429 and 5xx. Interactive requests (a visitor waiting on a page) use the
    | short profile; queued jobs use the long one and are retried again by
    | the queue.
    */
    'http' => [
        'connect_timeout' => (float) env('PAYMENT_HTTP_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('PAYMENT_HTTP_TIMEOUT', 15),
        'interactive' => ['attempts' => 2, 'base_delay_ms' => 250, 'max_delay_ms' => 1000, 'timeout' => 8],
        'background' => ['attempts' => 4, 'base_delay_ms' => 500, 'max_delay_ms' => 8000],
    ],

    // Circuit breaker per gateway + mode: after `threshold` consecutive
    // failures, calls fail fast for `cooldown_seconds` instead of tying up
    // workers on a gateway that is down.
    'circuit' => [
        'threshold' => (int) env('PAYMENT_CIRCUIT_THRESHOLD', 8),
        'cooldown_seconds' => (int) env('PAYMENT_CIRCUIT_COOLDOWN', 30),
    ],

    /*
    | Reconciliation (payments:reconcile, every minute): pending gateway
    | payments are checked with the gateway on this schedule (minutes after
    | creation / the previous check), so a missed webhook never leaves a paid
    | payment pending. After `give_up_after_minutes` an unpaid payment is
    | cancelled (after a final check).
    */
    'reconcile' => [
        'schedule_minutes' => [1, 2, 3, 5, 10, 15, 30, 60],
        'give_up_after_minutes' => (int) env('PAYMENT_GIVE_UP_AFTER_MINUTES', 120),
        'batch' => 500,
        // A status poll from the app checks the gateway at most this often per payment.
        'poll_check_seconds' => 8,
    ],

    // Retention of raw gateway logs (calls, webhook bodies). Payment events
    // (the timeline), sessions and refunds are kept.
    'log_retention_days' => (int) env('PAYMENT_LOG_RETENTION_DAYS', 400),

    // Queue for webhook, reconcile and refund jobs (run a worker on it first).
    'queue' => env('PAYMENT_QUEUE', 'payments'),
];
