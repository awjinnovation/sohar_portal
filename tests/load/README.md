# Load test: evening peak

[k6](https://k6.io) scenario of visitors opening the app (docs/caching.md):
the home screen's public requests in parallel, personal ones for the 70 %
signed in, then an event page. Ramping arrival rate up to `PEAK` app opens
per second.

Never run it against production without agreeing a window: it creates real
load (no writes).

```bash
# 1. A stack to test (local Docker or staging), seeded: php artisan db:seed --force
# 2. Signed-in visitors and event ids (in the app container):
php artisan tinker --execute='
$t = App\Models\Tenant::where("slug","north-batinah")->first();
app(App\Tenancy\TenantContext::class)->set($t);
$tokens = [];
for ($i = 0; $i < 300; $i++) {
  $u = App\Models\User::firstOrCreate(["phone_number" => "9689".str_pad((string) $i, 7, "0", STR_PAD_LEFT)],
    ["name" => "Load $i", "email" => "load$i@example.test", "password" => bcrypt(Str::random(20))]);
  $tokens[] = $u->createToken("load")->plainTextToken;
}
file_put_contents("/tmp/tokens.json", json_encode($tokens));
file_put_contents("/tmp/events.json", json_encode(App\Models\Event::where("is_active", true)->pluck("id")->take(20)->values()));'
# copy /tmp/tokens.json and /tmp/events.json next to peak.js (they are git-ignored)
# 3. Run:
BASE=http://127.0.0.1:18080/api/v1 PEAK=30 k6 run peak.js
```

Compare `API_CACHE_ENABLED=false` and `true`; watch `X-Cache` and MySQL's
`SHOW GLOBAL STATUS LIKE "Questions"`. Delete the load users afterwards.

## Visitors at the same time (`visitors.js`)

Signed-out visitors browsing like the app: home screen API batch, the
server's own images (each visitor downloads an image once), an event page,
the events and places lists, 15–25 s of reading between screens. Ramps to
`USERS` in three steps over 6 minutes, then holds for `HOLD` (default 14m).
Needs no extra files: event ids and image URLs are read from the API.

```bash
# Through Cloudflare (what visitors get):
k6 run -e BASE=https://v2-mawsim.awj.om -e USERS=300 visitors.js

# On the server itself (no Cloudflare, tunnel or internet line in the way):
docker run --rm --network host -v "$PWD":/t -w /t grafana/k6:0.57.0 run \
  -e BASE=http://v2-mawsim.awj.om -e HOST_IP=127.0.0.1 -e USERS=1000 visitors.js

# Smoke test: one visitor, no reading time
k6 run --vus 1 --iterations 2 -e THINK=0 visitors.js
```

`HOST_IP` sends the requests for `BASE`'s host name to that address (the
server's reverse proxy). Use `https://` in `BASE` if the proxy only answers
HTTPS (certificate checks are skipped with `HOST_IP`). The run stops early
only if more than half of the requests fail.
