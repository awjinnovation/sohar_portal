<?php

/*
| Public landing page (Mawsim) and its "Book a demo" form.
*/
return [
    // Where new demo requests are emailed.
    'demo_request_to' => env('DEMO_REQUEST_TO', 'systems@awj.om'),

    // Optional extra recipients (comma-separated).
    'demo_request_cc' => array_values(array_filter(array_map('trim', explode(',', (string) env('DEMO_REQUEST_CC', ''))))),

    // Send a short bilingual thank-you to the requester when they gave an email.
    'demo_request_confirmation' => (bool) env('DEMO_REQUEST_CONFIRMATION', true),

    // Spam protection: minimum seconds between page render and submit, and
    // how long a rendered form stays valid.
    'min_fill_seconds' => (int) env('DEMO_REQUEST_MIN_FILL_SECONDS', 3),
    'form_ttl_seconds' => (int) env('DEMO_REQUEST_FORM_TTL', 60 * 60 * 24 * 2),

    // Rate limits per IP.
    'rate_per_hour' => 5,
    'rate_per_day' => 20,

    // Timezone used for "submitted at" in the emails and admin list.
    'timezone' => 'Asia/Muscat',
];
