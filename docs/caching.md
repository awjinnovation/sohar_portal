# Caching and peak capacity

Target: about **20,000 visitors a day, almost all between 16:00 and 01:00**.
That is roughly 2,000–3,000 visitors in a busy hour, and short bursts several
times higher (event opening, a push notification, gates opening). Every app
open sends the home screen's 8–11 requests at once.

## Layers

| # | Layer | Where | What it saves |
|---|---|---|---|
| 1 | **Shared API response cache** (Redis) | `App\Http\Middleware\CachePublicApi`, `config/api_cache.php` | Database and serialisation for the public catalogue |
| 2 | **ETag / 304** | same middleware; app `ResponseCache` | Bandwidth and JSON parsing on repeat visits |
| 3 | Tenant config cache + ETag | `TenantConfigController` (existing) | The first call of every app start |
| 4 | Static files | NGINX `docker/nginx/*` (existing) | `/storage`, `/tenants`, `/images`: 7 days |
| 5 | OPcache, route / config / view / event caches | serversideup image, `AUTORUN_LARAVEL_OPTIMIZE` | PHP compile and boot time |
| 6 | Fewer writes per request | `App\Models\PersonalAccessToken` | `last_used_at` written at most every 5 min per token |
| 7 | Server sizing | `docker-compose.yml` | PHP-FPM, MySQL, Redis for the evening peak |

### 1. Shared API response cache

Public, user-independent GET endpoints (`routes/api.php`, `cache.api:<tier>`)
are stored per **tenant + language + URL** (query order does not matter):

| Tier | TTL | Endpoints |
|---|---|---|
| `static` | 10 min | config, categories, map locations, locations, app settings, legal pages, ticket pricing, places, content |
| `catalog` | 2 min | event and restaurant lists / details / search, announcements, public notifications |
| `live` | 20 s | events today, event availability, daily availability, tickets available, restaurants open now, active / priority announcements |

Not cached: `/tenant/config` (its own cache), `/locations/nearby` (per
coordinates), `/payments/quote` and every signed-in route (wallet, tickets,
points, notifications…). **Never** add `cache.api` to a route whose response
depends on the signed-in user.

- **Invalidation is immediate.** Saving or deleting a catalogue record
  (Event, ticket type, tag, Category, Announcement, Restaurant, MapLocation,
  Place, place category, content collection / item / page, public
  Notification, AppSetting, legal pages), an image of one, or the tenant's
  settings changes that tenant's cache version (after the transaction
  commits), so the next request reads fresh data. Other tenants keep their
  entries. Ticket sales do not invalidate anything (availability is counted
  from tickets and cached for 20 s at most).
- **No stampede.** When an entry is missing, one request computes it while
  the others wait up to 5 s for it (`lock_wait_seconds`), then share it.
- TTLs get ±10 % jitter so entries written together do not expire together.
- Responses carry `ETag`, `Cache-Control: no-cache` (always revalidate),
  `Vary: X-Tenant, Accept-Language` and `X-Cache: HIT | MISS | BYPASS`.
- Only 200 JSON responses without cookies are stored.
- Switch: `API_CACHE_ENABLED=false`; TTLs: `API_CACHE_TTL_STATIC`,
  `API_CACHE_TTL_CATALOG`, `API_CACHE_TTL_LIVE`.

### 2. In the app

The app's `ResponseCache` keeps the ETag of public responses and sends
`If-None-Match`; a 304 is answered from the stored body (also used offline).

### 7. Server sizing (`docker-compose.yml`, all overridable in Coolify)

| Setting | Default | Notes |
|---|---|---|
| `PHP_FPM_PM_CONTROL` | `dynamic` | Workers are pre-forked; the image default `ondemand` forks under a burst. |
| `PHP_FPM_PM_MAX_CHILDREN` | 40 | ~40–60 MB each. About 10 per vCPU is a good start. |
| `PHP_FPM_PM_START_SERVERS` / `MIN_SPARE` / `MAX_SPARE` | 12 / 8 / 20 | |
| `PHP_FPM_PM_MAX_REQUESTS` | 1000 | Recycles a worker after 1000 requests. |
| `MYSQL_INNODB_BUFFER_POOL_SIZE` | 1G | The whole working set fits in memory. |
| `MYSQL_MAX_CONNECTIONS` | 300 | Above all PHP-FPM workers + queue workers. |
| `REDIS_MAXMEMORY` | 768mb | Policy `volatile-lru`: only keys with a TTL (cache entries, rate limits, sessions) can be evicted, never queued jobs. |

Recommended server for this target: **4 vCPU, 8 GB RAM** (PHP-FPM ≈ 2.5 GB at
40 workers, MySQL ≈ 1.5 GB, Redis ≤ 0.8 GB, worker + scheduler + OS ≈ 1 GB).
For more, add CPU and raise `PHP_FPM_PM_MAX_CHILDREN`, or add a second worker
service for the queue (DEPLOY_COOLIFY.md §12).

## Measured (local Docker, production image)

Stack: the `docker-compose.yml` services (app, worker, scheduler, MySQL 8.4,
Redis 7) with the seeded North Batinah data, on a 16-core / 8 GB Docker host.
Load: k6, visitors opening the app: the home screen's 8 public requests in
parallel, plus 3 personal ones for the 70 % who are signed in, then an event
page with its availability.

| Run | Requests | Rate | Public p95 | Personal p95 | Errors |
|---|---|---|---|---|---|
| 30 app opens/s, cache **off** | 34,655 | 262/s | 30.6 ms | 9.1 ms | 0 |
| 30 app opens/s, cache **on** | 34,801 | 265/s | **5.0 ms** | 5.8 ms | 0 |
| 100 app opens/s (~1,100/s at the top), cache **off** | 114,591 | 865/s | 34.7 ms | 8.8 ms | 0 |
| 100 app opens/s, cache **on** | 114,842 | 867/s | **5.5 ms** | 6.5 ms | 0 |

Database work per app open (10 public requests): **335 queries without the
cache, 41 with it** (−88 %). The remaining ones are connection setup only; a
cached response runs no data query.

30 app opens per second is about ten times the average of a 3,000-visitor
hour, so the evening peak has a wide margin. On a 4-vCPU server the database
saving matters most: without the cache every app open costs ~335 queries.

Re-run: `tests/load/peak.js` and `tests/load/README.md`.

## Not cached on purpose

- Ticket checkout, quotes, payments, wallet, points, tickets: always live.
- Gate scanning uses its own offline manifest (ETag, `/gate/manifest`).
