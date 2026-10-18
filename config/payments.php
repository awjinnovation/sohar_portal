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

    // Max tickets per checkout.
    'max_tickets_per_order' => 10,
];
