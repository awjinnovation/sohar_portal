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
