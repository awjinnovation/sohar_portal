<?php

/*
| Event booking site (booking-web/: React build on its own domain, its own
| Docker image). It opens /e/{event} from the event's printed QR and talks to
| /api/v1/booking/events/{event}/* (Api\BookingController).
|
| BOOKING_WEB_URL set (e.g. https://book.mawsim.awj.om):
|   - the printed booking QR points there (VisitorBookingController::publicUrl);
|   - the old page on this server (/e/{event}) redirects there, so QR codes
|     printed before the switch keep working;
|   - card payments made on the page return there (?payment=…).
| Empty: the page on this server (resources/views/visitor) stays in use.
*/
return [
    'web_url' => rtrim(trim((string) env('BOOKING_WEB_URL', '')), '/'),

    // Sign-in token of the booking site (a Sanctum token kept in the browser).
    'token_days' => (int) env('VISITOR_SESSION_DAYS', 30),

    // Public event payload (GET /api/v1/booking/events/{event}): seconds a
    // CDN in front of the API may keep it (s-maxage). Browsers revalidate
    // every time (cheap 304 on the ETag). Availability is checked again at
    // checkout, so a few seconds of staleness never oversells.
    'cdn_max_age' => (int) env('BOOKING_CDN_MAX_AGE', 15),
];
