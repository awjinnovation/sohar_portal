<?php

return [

    /*
    | Payment gateway driver: "bankmuscat" (CCAvenue non-seamless, production)
    | or "fake" (local page with Simulate success / failure buttons, for
    | development and automated tests). The fake driver is refused when
    | APP_ENV=production.
    */
    'driver' => env('PAYMENT_DRIVER', 'bankmuscat'),

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
];
