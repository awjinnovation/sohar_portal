<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Same as the framework default (the API is public, token auth, no
| cookies) plus the public image paths, so the Flutter web builds (gate web
| app, visitor web) can load them from another origin: `storage/*`
| (uploads: places, content, tenant logos), `tenants/*` (bundled tenant
| branding: logos, marks, onboarding) and `images/*`. Only GET / HEAD
| matter for those; nothing else is opened.
|
| Static files are usually served by the web server without PHP, so this
| file only covers what reaches Laravel: `php artisan serve` uses
| ./server.php (adds the headers for the same paths), the Docker image's
| NGINX adds them from the same CORS_ALLOWED_ORIGINS
| (docker/nginx/public-assets.conf).
|
| CORS_ALLOWED_ORIGINS: comma-separated origins of the web builds (e.g. the
| gate web app, a visitor web build, the booking site), or "*" (default).
| Native mobile apps do not use CORS.
|
| CORS_MAX_AGE: seconds a browser keeps a preflight answer (default 600). A
| call with a token or a JSON body is preflighted; 0 would mean one extra
| OPTIONS round trip before every such call.
|
*/

$origins = array_values(array_filter(array_map(
    fn ($o) => rtrim(trim($o), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*', 'tenants/*', 'images/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ($origins === [] || in_array('*', $origins, true)) ? ['*'] : $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag'],

    'max_age' => (int) env('CORS_MAX_AGE', 600),

    'supports_credentials' => false,

];
