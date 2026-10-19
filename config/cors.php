<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Same as the framework default (the API is public, token auth, no
| cookies) plus `storage/*`, so the Flutter web builds can load uploaded
| images (places, content, branding) from another origin. Only GET / HEAD
| matter for storage; nothing else is opened.
|
| Static files under public/storage are usually served by the web server
| without PHP: `php artisan serve` uses ./server.php (adds the same headers
| for /storage/*), production web servers need the equivalent rule (see
| README → Places & content → CORS).
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag'],

    'max_age' => 0,

    'supports_credentials' => false,

];
