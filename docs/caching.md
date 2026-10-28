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

## Capacity of a 16-core / 64 GB server

Measured on 28 Oct 2026, with the app container capped at 4 and then 8 CPUs.
The traffic was the same app-open mix with the cache on, and some runs added
wallet ticket purchases.

| Cost per request | Value |
|---|---|
| PHP (nginx + PHP-FPM) | 3.1–3.7 ms CPU, the same at 4 and 8 CPUs |
| MySQL | ~0.7 ms CPU |
| Redis | ~0.1 ms CPU |
| Requests per app open | ~12 |

At 4 CPUs the app saturated at about **1,290 requests/s**, which matches
4 CPUs ÷ 3.1 ms. Above about 1,300 requests/s the test machine itself ran out
of connections (macOS ports, Docker Desktop port forwarding), so the 16-core
figures below are calculated from the measured cost per request.

| 16 cores (≈ 4.3–4.5 ms CPU per request in all; ~0.75 core kept for OS, worker, scheduler) | Per second | Concurrent active users* |
|---|---|---|
| CPU as fast as the test machine (Apple M4 Max) | ~3,300 requests (~275 app opens) | ~25,000 |
| Typical server vCPU (assume 0.5–0.7 of that) | **1,700–2,300 requests (140–190 app opens)** | **13,000–17,000** |
| Comfortable, at 70 % CPU | 1,200–1,600 requests (100–130 app opens) | **9,000–12,000** |

\* A visitor with the app open sends about 8 requests a minute on average
(home screen, a few screens, status polls), so one request/s ≈ 7.5 active
users. A push notification that makes 6,000 people open the app within a
minute is ~100 app opens/s (~1,200 requests/s), which is within the
comfortable range.

The daily target (20,000 visitors, mostly 16:00–01:00) is far below this.
The busiest hour is a few thousand visitors, with a few hundred to a
thousand using the app at the same moment.

**Purchases** are limited per event, not by CPU. Purchases of one event are
serialised by a row lock (about 20–25 ms each), so an estimated 40
purchases/s per event at most. Different events run in parallel.

**Above capacity** requests queue inside PHP-FPM until the client gives up
(60 s), so latency climbs for everyone rather than a few requests failing.
Keep peak CPU under ~70 %, and confirm with `tests/load/peak.js` from
another machine before the event.

Settings for this server (Coolify environment variables):

| Variable | Value |
|---|---|
| `PHP_FPM_PM_MAX_CHILDREN` | 160 (≈ 10 GB at ~60 MB each) |
| `PHP_FPM_PM_START_SERVERS` / `MIN_SPARE` / `MAX_SPARE` | 48 / 32 / 64 |
| `MYSQL_INNODB_BUFFER_POOL_SIZE` | 8G |
| `MYSQL_MAX_CONNECTIONS` | 500 |
| `REDIS_MAXMEMORY` | 2gb |

Also add a second queue worker (`worker-2: *worker`, see `docker-compose.yml`).

Tested and rejected: persistent Redis connections (`REDIS_PERSISTENT=true`)
used ~40 % more PHP CPU at the same load, so they stay off.

## Connections: no port per request

PHP-FPM opens its database and Redis connections per request and closes
them at the end. Over TCP, Linux keeps each closed connection's port for 60
seconds (TIME_WAIT), and a container has 28,232 source ports per
destination: about 470 new connections a second. On staging (28 Oct 2026)
that stopped a test at ~2,000 visitors (~450 requests/s) with `Cannot assign
requested address`, while the server used 10 % of its CPU.

- **MySQL and Redis use Unix sockets** (`docker-compose.yml`: volumes
  `mysql-socket`, `redis-socket`; `DB_SOCKET`, `REDIS_HOST`, `REDIS_PORT=0`).
  No ports, no DNS lookup, nothing kept between requests. For a database on
  another server, set `DB_SOCKET=` and `REDIS_HOST=redis`, `REDIS_PORT=6379`.
- **The tenant lookup is cached** (`Tenant::findBySlug`, 60 s, cleared when
  the tenant is saved), so a response from the API cache runs no SQL at all.
- **Safety net** for the remaining TCP (Thawani, SMS, AI): app, worker and
  scheduler get 55,000 source ports and `tcp_tw_reuse` (`x-net-sysctls`).

Local Docker, production image, 800 requests/s for 90 s with a large
server's TIME_WAIT limit:

| | TCP (before) | Sockets (after) |
|---|---|---|
| Failed requests | 32 % (`Cannot assign requested address`) | 0 % |
| Ports held for MySQL / Redis | 28,232 / 28,231 (all) | 0 / 0 |
| Median / p95 / p99 | 4 / 7 / 13 ms | 2 / 3 / 6 ms |

## Not cached on purpose

- Ticket checkout, quotes, payments, wallet, points, tickets: always live.
- Gate scanning uses its own offline manifest (ETag, `/gate/manifest`).
