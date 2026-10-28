<?php

/*
| Shared response cache for the public, user-independent API (catalogue:
| events, announcements, places, content, restaurants, map, legal…).
| docs/caching.md. Entries are per tenant + language + URL, kept in the
| default cache store (Redis in production), and dropped at once when an
| admin changes a catalogue record (the tenant's cache version is bumped).
*/
return [
    'enabled' => (bool) env('API_CACHE_ENABLED', true),

    // Seconds per tier (route middleware cache.api:<tier>).
    'ttl' => [
        'static' => (int) env('API_CACHE_TTL_STATIC', 600),   // categories, config, legal, content, places, map
        'catalog' => (int) env('API_CACHE_TTL_CATALOG', 120), // event / restaurant lists and details, announcements
        'live' => (int) env('API_CACHE_TTL_LIVE', 20),        // availability, today, open now
    ],

    // ± share of the TTL, so entries written together don't expire together.
    'jitter' => 0.1,

    // A miss is computed by one request; the others wait this long for it.
    'lock_wait_seconds' => 5,
];
