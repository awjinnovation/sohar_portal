<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Read by Laravel's TrustProxies middleware. Requests whose REMOTE_ADDR is
| in this list may set X-Forwarded-For / -Host / -Port / -Proto, so URLs,
| redirects and secure cookies use the public https://… address and rate
| limits see the visitor's IP.
|
| The default covers a reverse proxy on a private Docker network (Coolify's
| Traefik / Caddy) and localhost. The container's NGINX leaves REMOTE_ADDR
| alone (TRUSTED_PROXY=off in the Dockerfile), so it is this list that
| decides. Behind Cloudflare (orange cloud) add Cloudflare's ranges; "*"
| trusts whoever connects and must only be used when the app is reachable
| exclusively through the proxy.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1,::1,fd00::/8'),

];
