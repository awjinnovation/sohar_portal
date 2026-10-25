# Events Platform — Backend (Laravel 12)

White-label, multi-tenant events platform: events & ticketing, closed-loop
**wallet** (top-ups, vendor payments, refundable deposits, refunds), **loyalty
points**, vendor/merchant tools and a tenant-branded Blade admin panel.
One backend serves many organizers ("tenants"); the Flutter app
(`../sohar_north_app`) is built once per tenant and themed at runtime from
`GET /api/v1/tenant/config`.

The API contract shared with the app lives in
[`../docs/platform_contract.md`](../docs/platform_contract.md) (§12 lists the
backend's implementation notes). Endpoint reference with examples:
[`business-data/PLATFORM_API.md`](business-data/PLATFORM_API.md).

## Architecture

| Area | Where | Notes |
|---|---|---|
| Tenancy | `app/Tenancy/*`, `app/Models/Tenant.php`, `config/tenancy.php` | Single DB, `tenant_id` on every tenant-owned table. `BelongsToTenant` trait = global `TenantScope` + auto-fill on create. `TenantContext` holds the current tenant (falls back to `config('tenancy.default')`). |
| Tenant resolution | `app/Http/Middleware/ResolveTenant.php` (API), `AdminMiddleware.php` (admin) | API: `X-Tenant: <slug>` header, default `north-batinah`; unknown/inactive → 404 `TENANT_NOT_FOUND`. Admin: tenant chosen at login / switcher (session). |
| Tenant settings | `tenants.settings` JSON deep-merged over `config('tenancy.defaults')` | Branding, theme, modules, navigation, map, wallet, points, app versions, billing address, OTP SMS text. `tenants.gateway_credentials` is an `encrypted:array` (Bank Muscat per tenant, falls back to `config/services.php`). |
| Users & roles | `users` (global identities by phone), spatie/permission **teams** (team = tenant) | Role definitions are global; assignments are per tenant. Platform super-admins: `users.is_super_admin`. Roles: `admin`, `event-manager`, `ticket-manager`, `content-manager`, `finance-manager`, `viewer`, `staff` (gate scanner), `vendor-staff`, `user`. |
| Money | `app/Services/Wallet/*` | Integer minor units only (`*_minor`). `WalletService` is the only code that moves money: DB transaction + `lockForUpdate`, append-only `wallet_transactions` ledger with `balance_after_minor`, idempotency key unique per wallet, typed `ApiException`s → contract error codes. Buckets: **cash** (top-ups, refundable) and **promo** (points, bonuses, goodwill; spent first, never refunded). Deposits are holds (`available = balance − held`). |
| Points | `PointsService` | Earn on completed ticket purchases and vendor payments (`floor(major × earn_per_currency_unit × tier multiplier)`), FIFO lots with expiry (`points:expire` daily), redeem into promo credit, reversed on refunds, optional check-in reward. |
| Payments | `app/Services/Payments/*` | `CheckoutService` (server-side pricing, quote, wallet/gateway checkout), `TopupService`, `PaymentFulfillmentService` (single idempotent completion path for webhook + redirect + fake driver), `TicketIssuer` (one place tickets are created), `BankMuscatCallbackHandler` (decrypt with the tenant key, verify order/currency/amount). |
| Merchant | `MerchantService`, `PayTokenService`, `DepositService` | Signed single-use 60 s pay tokens (QR), vendor charges, deposits. |
| Admin | `app/Http/Controllers/Admin/*`, `resources/views/admin/*` | Bootstrap 5, branded from the current tenant; Arabic (RTL) by default with an EN/AR switch (session `admin_locale`, `SetAdminLocale` middleware). Strings are Arabic source text translated via `lang/en.json` (`__('…')`; admin components translate their props). Tenants (super-admin), branding & settings, wallets (ledger, adjustments, freeze; opening a user without a wallet never creates one — POST does), points (accounts, tiers, history, audited adjustments, rules), refund requests, vendors + staff, deposits, payments (refunds), plus the event/content CRUDs. Dashboard figures: `App\Services\Admin\DashboardMetrics`. Routes are guarded with `can:<permission>`. |
| Time | `App\Support\TenantTime`, `App\Casts\TenantLocalDateTime` | Instants are stored in UTC; event / announcement schedule times are stored as tenant wall-clock. All API datetimes are ISO 8601 with the tenant offset. |
| Audit | `audit_logs` (`App\Models\AuditLog::record`) | Manual adjustments, freezes, refunds, refund decisions. |
| Ticket types | `App\Models\EventTicketType`, `CheckoutService::quoteItems`, `TicketIssuer`, `Admin/EventTicketTypeController` | Per-event types (General, Family admitting 4, VIP, Child, Senior…) with price, people per ticket, own capacity, per-order limit, ages, ID check, sales window, app or gate-only visibility, colour, sort. See *Ticket types* below. |
| Box office | `BoxOfficeService`, `Api/GateSalesController`, `Web/BoxOfficePayController`, `Admin/GateSalesController` | Gate staff sell tickets; the visitor pays through `/pay/{token}`. See *Gate box office* below. |
| Gates | `app/Services/Gate/*`, `Api/GateController`, `Admin/Gate*Controller`, `AuthenticateGateDevice` | Gate scanner: paired devices (Sanctum token of `GateDevice`, ability `gate`), `TicketScanService` = the only ticket-validation logic (gate app, offline sync, legacy staff screen), salted offline manifest, live gate dashboard. See *Gate scanner* below. |

### Request flow example — wallet ticket checkout
`POST /api/v1/payments/initialize {method: wallet}` → `ResolveTenant` →
`CheckoutService::initialize` (locks the event, computes price, optional
points discount) → `WalletService::debit` (promo first) →
`PaymentFulfillmentService::complete` → `TicketIssuer` + deposit holds +
points earn — all in one DB transaction, idempotent per `idempotency_key`.

## Getting started

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

For local end-to-end flows set in `.env`:

```dotenv
PAYMENT_DRIVER=fake      # payment_url opens a local "Simulate success / failure" page
OTP_DEBUG=true           # send-otp returns the code (ignored in production)
OTP_TEST_CODE=123456     # optional fixed OTP outside production
```

### Seeded tenants and accounts (password for all: `password123`)

| Tenant (`X-Tenant`) | Admin panel | App staff (phone, OTP login) |
|---|---|---|
| — platform — | `super@platform.local` (super-admin, can switch tenants) | |
| `north-batinah` (default) | `admin@northbatinah.events` (admin), `events@…` (event-manager), `finance@…` (finance-manager) | gate staff `90000001`, vendor cashier `90000002` (vendor "Sohar Karak House") |
| `demo` ("Demo Events", English-first, indigo theme, map off, points on, top-up bonus) | `admin@demo-events.example` | vendor cashier `90000003` (vendor "Expo Food Court"), gate staff `90000004` |

Demo visitor: phone `91234567` — stored with the country code as `96891234567`, which is what the app sends; staff numbers likewise (wallet: 10.000 OMR in north-batinah, 20.000 OMR in demo).
**Change all seeded passwords before any shared deployment.**

### Fake payment flow (PAYMENT_DRIVER=fake)
1. `POST /api/v1/wallet/topups {"amount_minor":10000,"idempotency_key":"<uuid>"}` (or `/payments/initialize` with `method: gateway`) → `data.payment_url`.
2. Open the URL in a browser → buttons **Simulate success / failure / Cancel**.
3. Success runs the same fulfillment as the Bank Muscat webhook (wallet credited once / tickets issued once) and redirects to `/payment/success`.
4. Poll `GET /api/v1/wallet/topups/{id}` or `GET /api/v1/payments/status/{transaction_id}`.
The fake driver refuses to run when `APP_ENV=production`.

## Public landing page (Mawsim) and demo requests

`GET /` is the product homepage (`LandingController@index`, `resources/views/landing/index.blade.php`, assets in `public/landing/` with `?v=filemtime` cache busting; no build step). It is bilingual: the server picks the first-paint language from `?lang=ar|en`, then the `mawsim_lang` cookie (also written by the page's EN/AR toggle), then `Accept-Language`, and sets `<html lang dir>` accordingly; the JS dictionary in `landing.js` renders the copy. Staff reach the admin through the footer's "Staff sign in" link (`/admin/login`). Content and meta match the standalone `../landing_page` (field photos, Open Graph / Twitter cards with `landing/img/og-image.jpg` 1200×630, JSON-LD, icons + `landing/site.webmanifest`, self-hosted fonts in `landing/fonts*`, no outside requests); canonical, link-preview and JSON-LD URLs are absolute from `APP_URL`. `/robots.txt` and `/sitemap.xml` are routes built from `APP_URL` (outside production robots disallows everything and the page is `noindex`).

**Book a demo** posts to `POST /demo-request` (`landing.demo-request`): JSON via `fetch` when JS is on (inline, localized errors), or a normal form POST that redirects to `/#demo` with a server-rendered confirmation. Fields use stable keys (`event_type`: festival/expo/concerts/mall/governorate/sports/other, `expected_visitors`: lt5k/5k-25k/25k-100k/gt100k); `contact` must be an email or a phone with 7–15 digits. Spam protection: hidden `website` honeypot (silently "succeeds"), a signed render timestamp (`App\Support\Landing\FormTimestamp`, rejected under 3 s or after 2 days) and the `demo-requests` rate limiter (5/hour and 20/day per IP).

Each request is stored first in the platform-level `demo_requests` table (`App\Models\DemoRequest`, not tenant-scoped; IP stored only as `sha256(ip|APP_KEY)`), then mailed synchronously inside `try/catch` (a mail failure is logged and never fails the submission; `mailed_at` is set on success):
- `App\Mail\DemoRequestReceived` → `DEMO_REQUEST_TO` (+ `DEMO_REQUEST_CC`), Reply-To = the requester when they gave an email (`resources/views/emails/demo-request{,-text}.blade.php`).
- `App\Mail\DemoRequestConfirmation` → the requester (only when the contact is an email and `DEMO_REQUEST_CONFIRMATION=true`), in their language (Arabic RTL), Reply-To = `DEMO_REQUEST_TO`.

Super-admins manage leads at **Admin → Platform → Demo requests** (`/admin/demo-requests`: search, status filter, detail, status new/contacted/closed; sidebar badge = new requests). Tenant admins get 403 (`manage demo requests` is a platform ability).

### Mail delivery
With `MAIL_MAILER=log` (the local default) **nothing is delivered**: the full message is written to `storage/logs/laravel.log`. To deliver to systems@awj.om, configure SMTP (or another Laravel mailer) in `.env`, e.g.:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps          # or null + MAIL_PORT=587 for STARTTLS
MAIL_HOST=smtp.your-provider.example
MAIL_PORT=465
MAIL_USERNAME=no-reply@awj.om
MAIL_PASSWORD=…            # app password / API key
MAIL_FROM_ADDRESS="no-reply@awj.om"
MAIL_FROM_NAME="Mawsim"
DEMO_REQUEST_TO=systems@awj.om
```

The from-domain (awj.om) needs SPF/DKIM (and ideally DMARC) records for the sending provider, otherwise the mails may land in spam. Then `php artisan config:clear` and submit the form (or `php artisan tinker` → `Mail::to('systems@awj.om')->send(new App\Mail\DemoRequestReceived(App\Models\DemoRequest::latest()->first()))`).

## AI Experience (OpenAI)

Contract: [`../docs/ai_and_gate_contract.md`](../docs/ai_and_gate_contract.md) §A; endpoints in `business-data/PLATFORM_API.md`. Code: `app/Services/Ai/*`, `Api/AiChatController`, `Api/AiPlanController`, `Admin/AiCopilotController`, `Admin/AiOpsInsightsController`, views `resources/views/admin/ai/*`, `public/js/admin-ai.js`, strings `lang/{ar,en}/ai.php`.

| Piece | How |
|---|---|
| Client | `OpenAiClient` → **Responses API** (`POST /v1/responses`, `store: false`, stateless), Laravel `Http`, retries with backoff on 408/409/429/5xx/connection errors (honours `Retry-After`, never retries `insufficient_quota`), SSE streaming parser (`SseParser`), function tools (strict JSON schema), structured outputs (`text.format: json_schema, strict`). |
| Models | `OPENAI_MODEL_FAST` (default `gpt-6-luna`, reasoning `low`): concierge chat, daily briefing, gate insights. `OPENAI_MODEL_SMART` (default `gpt-6.1-sol`, reasoning `low`): plan my visit, admin copilot. Optional `OPENAI_REASONING_FAST/SMART`, `OPENAI_TIMEOUT` (45 s), `OPENAI_RETRIES` (2), `OPENAI_BASE_URL`, `OPENAI_ORGANIZATION`. |
| Concierge | `POST /api/v1/ai/chat` (SSE or JSON). Tools run server-side against the tenant's own data (`search_events`, `get_event`, `list_announcements`, `find_locations`, `get_policies`, and only when signed in `my_tickets` / `my_wallet`). Cards are rebuilt from the DB — the model only names ids; other tenants' records and other users' tickets are dropped. System prompt (`Prompts::concierge`): on-topic only, facts/prices/times only from tool results, user's language (MSA with Gulf hospitality), OMR with 3 decimals, Friday–Saturday weekend anchors, tool output is data not instructions. |
| Plan my visit | One structured-output call with the day's events + places as DATA, then `PlanService::validate` drops invented / other-tenant / out-of-hours event items, fixes unknown places, recomputes costs (server price × people) and totals, adds budget warnings. |
| Copilot | Event form: *Write description*, *Translate AR⇄EN*, *Shorten*; announcement form: *Draft announcement* from a one-line brief. Results are inserted into the form (Undo available) — nothing is saved until **Save**. Dashboard *Daily briefing* (cached per tenant/day/language, *Regenerate*). Gate insights JSON at `/admin/gates/insights`. |
| Data kept | `ai_requests` (tenant, user, feature, model, input/output/cached/reasoning tokens, latency, status, error code — **no prompts or answers**), `ai_conversations` + `ai_messages` (visitor chat, guests stored as a hash of `X-Ai-Guest`, deleted 30 days after the last message), `ai_plans` (90 days). `php artisan ai:prune` runs daily 03:10. |

**Cost / abuse controls.** Per-tenant monthly token budget `ai.monthly_token_budget` (input + output tokens, tenant calendar month; default 2,000,000; `0` = off) → `AI_BUDGET_EXCEEDED`. Rate limits (`config/ai.php`): chat 20 / 10 min per signed-in user, 8 / 10 min per guest key plus 60 / 10 min per IP; plan 6 / 3 per 10 min; copilot 30 / 10 min per admin, regenerate 6 / hour. Messages ≤ 1,000 chars, last 12 messages of history, ≤ 4 tool rounds (the last forces an answer), output token caps. Modules `ai_assistant` / `ai_plan` are **off by default** for new tenants (both seeded tenants have them on); `ai.copilot_enabled` switches the admin features. Without `OPENAI_API_KEY` every AI endpoint answers `AI_UNAVAILABLE` and the admin buttons are hidden.

**Costs (measured locally).** A concierge turn is 2 calls ≈ 2.5–4k input + 100–150 output tokens on `gpt-6-luna` (≈ $0.0004 at $0.10 / $0.50 per 1M); a plan ≈ 2.8k in + 0.9k out on `gpt-6.1-sol` (≈ $0.015); a copilot translation ≈ 400 tokens. The default 2M-token budget is ≈ 600 chat turns a month — raise it for large festivals (e.g. 50M ≈ 15k turns ≈ $6–8 on luna). *Admin → Branding & settings → AI assistant* shows this month's usage and an estimated USD figure (`config('ai.prices')`).

**Tenant settings** (`ai` block; editable in the admin): `assistant_name`, `welcome`, `suggestions` (public, in `/tenant/config` → `ai`), `monthly_token_budget`, `copilot_enabled`, `venue_info` {en, ar} (free text the concierge may quote: opening hours, gates, parking).

## Places to visit & content (Discover)

Contract: [`../docs/places_content_contract.md`](../docs/places_content_contract.md) (§6 = implementation notes); endpoints in `business-data/PLATFORM_API.md` (*Places to visit & content*). Modules `places` and `content` (off for new tenants, on for both seeded tenants).

| Piece | How |
|---|---|
| Data | `place_categories` (key, name {en, ar}, Material Symbols icon, colour, sort, active), `places` (+ `event_place` pivot), `content_collections` (layout `list\|grid\|logos\|cards`), `content_items` (linked place or own lat/lng, tags), `content_pages`. All `BelongsToTenant`; localized fields are JSON `{en, ar}` stored with unescaped Unicode (`App\Models\Concerns\LocalizedContent`: `t()`, `published()` scope incl. scheduled `published_at`, unique slugs, bilingual LIKE search). |
| Media | spatie medialibrary on the `public` disk: places `cover` + `gallery`, items `image` (logo) + `gallery`, pages `cover`. `thumb` conversions run synchronously (`nonQueued`, GD, webp; logos `contain`, others 640×420 crop), original width/height in custom properties. `App\Support\MediaPresenter` builds `{id, url, thumb_url, width, height}` from the request host. Needs `php artisan storage:link`. |
| API | `Api\PlaceController`, `Api\ContentController`, resources in `app/Http/Resources/Discover/*`. `near=lat,lng` sorts with a PHP haversine (`App\Support\Geo`, portable across sqlite/mysql). `/places/map` has an ETag. Public share page `/discover/places/{slug}?tenant=&lang=`. |
| HTML | `App\Support\HtmlSanitizer` (DOM allow-list: p, br, b/strong, i/em, u, lists, h3/h4, blockquote, http/https/mailto/tel links), applied on save and on output. |
| Admin | Sidebar *Content*: Places (search, filters, featured star, drag-to-sort), place form (AR⇄EN tabs, light rich-text editor, category + Material Symbols icon picker, Leaflet 1.9.4 + OpenStreetMap location picker with paste-coordinates / map links / map centre, cover + gallery with drag reorder and remove, linked events, card preview, AI copilot *Write description* / *Translate*), Place categories, Places map (legend toggles, click to edit), Collections → items (logo/image, linked place or own location), Pages. Permissions `manage places` / `manage content` (tenant admins, `content-manager`). Views `resources/views/admin/{places,place-categories,content-*}`, components `x-admin.{lang-tabs,rich-text,icon-picker,location-picker,media-gallery,status-select}`, assets `public/{css,js}/admin-discover.*` (Leaflet + SortableJS from cdnjs, Material Symbols Rounded from Google Fonts). |
| AI | Concierge tools `find_places` / `search_content` and `place` / `content` cards (module-gated, tenant-scoped, rebuilt from the DB); reply language follows the visitor's message. Copilot `POST /admin/ai/copilot/content-description`. |
| Seed | `PlacesContentSeeder` (idempotent, `firstOrCreate` by key/slug, admin edits survive; Arabic rewording via `content:sync-arabic`). north-batinah: real places only, facts and coordinates from `../docs/north_batinah_seed_sources.md`, opening hours / fees / phones left empty, only Sohar Fort has a photo; collections *Visitor services*, *Partners* (empty on purpose), *Visitor guide*; pages *About North Al Batinah*, *Visiting guidelines*. demo: fictional places around Demo Arena, fictional brands. |

**Seeded app entry points.** The tabs are unchanged; Discover is reached from the home blocks `places_featured`, `places_map_teaser`, `collection:<key>`, the `places` quick action (demo) and More.

**CORS (web builds).** `config/cors.php` covers `api/*`, `storage/*` (uploads), `tenants/*` (bundled tenant branding) and `images/*`, with the origins from `CORS_ALLOWED_ORIGINS` (comma-separated, default `*`). Images are static files, so the web server must add the header itself: `php artisan serve` uses `./server.php` (adds `Access-Control-Allow-Origin: *` to the same paths); the Docker image's NGINX does it from `CORS_ALLOWED_ORIGINS` (`docker/nginx/public-assets.conf`).

## Gate scanner

Contract: [`../docs/ai_and_gate_contract.md`](../docs/ai_and_gate_contract.md) §B (implementation notes in §B5); endpoints in `business-data/PLATFORM_API.md` (*Gate scanner*). The scanner app (Flutter, `lib/gate/**` in the app repo) talks only to `/api/v1/gate/*`.

| Piece | How |
|---|---|
| Data | `gates` (name {en, ar}, code, optional `event_id`, `direction` entry/exit/both, `allow_reentry`, optional `ticket_types` e.g. `["vip"]`, `active`), `gate_devices` (type, model, platform, app version, last scanner source, status active/revoked, `last_seen_at`, battery, queue size, logged-in staff), `gate_pairing_codes` (6 chars from `A–Z2–9` without 0/O/1/I/L, 10 min, single use), `gate_scans` (append-only log, unique `idempotency_key`, `offline`, `conflict`). All tenant-scoped (`BelongsToTenant`). |
| Device auth | The **device is the principal**: pairing returns a Sanctum personal access token of the `GateDevice` with the single ability `gate`. `AuthenticateGateDevice` accepts only such tokens, sets the device's tenant as the request tenant (a conflicting `X-Tenant` → 403) and rejects revoked devices with `401 DEVICE_REVOKED`. A `Sanctum::authenticateAccessTokensUsing` hook (`GateServiceProvider`) makes `auth:sanctum` refuse device tokens, so a stolen scanner token cannot touch wallets or tickets. Staff may additionally log in on the device with the normal OTP (`/gate/staff/login`); no user token is issued. |
| Validation | `TicketScanService::scan()` — row lock on the ticket, idempotency per key, gate rules (event, ticket types, direction, re-entry, optional "exit before re-entry"), dates in tenant-local time with a **gate day** that rolls over at 04:00 (late-night events), early entry 60 min before the event start, refunded vs cancelled, check-in points (`points.earn_on_checkin`). `/gate/scan`, `/gate/sync` and the legacy `/tickets/validate` + `mark-used` all call it. |
| Offline | `GET /gate/manifest` — today's tickets for the gate as `sha256(salt + qr_code)` (salt = HMAC(APP_KEY, tenant, day); no codes leave the server), `ETag` / `If-None-Match`. The device admits locally, queues scans and `POST /gate/sync`s them (≤ 500, idempotent); the server flags `conflict` when a ticket was admitted elsewhere first. |
| Admin | Sidebar **Gates** (permission `manage gates`: tenant admins, `ticket-manager`): *Live gate dashboard* (polls every 15 s: in / out / inside / rejects / conflicts / devices online, entries per 5 min per gate (Chart.js), reject reasons, conflicts, recent scans, AI insights card from `/admin/gates/insights` — hidden when AI is off or failing), *Gates* CRUD, *Scanner devices* (status online ≤ 2 min / idle ≤ 15 min / offline, last seen, battery, sync queue, version, staff; **Pair new device** modal with the code + QR of `mawsim-gate://pair?code=XXXXXX&tenant=<slug>&api=<server root>` rendered server-side as SVG; rename / move / revoke), *Scan log* (date, gate, device, result, direction, source, offline / conflict, ticket number). EN strings: `lang/gate/en.json`; API messages: `lang/{ar,en}/gate.php`. |

**Pairing a device (local).** Seeded gates: north-batinah `A` Main Gate A, `B` Family Gate B (re-entry), `C` VIP Gate C (`vip` tickets only, re-entry), `EXIT1` Exit 1 (Beach Road) plus one demo device record "Main Gate A · Lane 1" (no token); demo `H1` Hall Entrance, `H2` Hall Exit. Pairing codes expire, so none is seeded — print one:

```bash
php artisan gate:pair-code north-batinah A --api=http://192.168.1.20:8002   # code + deep link (default 10 min, --minutes=…)
curl -X POST http://127.0.0.1:8002/api/v1/gate/pair -H 'Content-Type: application/json' -H 'Accept: application/json' \
     -d '{"pairing_code":"ABC123","device":{"name":"Lane 2","device_type":"android_pos","model":"Sunmi V2s","platform":"Android 11","app_version":"1.0.0"}}'
# → data.device_token; then: curl -H "Authorization: Bearer <token>" http://127.0.0.1:8002/api/v1/gate/session
```

Or *Admin → Gates → Scanner devices → Pair new device* and scan the QR with the gate app. Gate staff log in with phone `96890000001` (north-batinah) and the OTP flow. The demo tenant has the `staff_scanner` module **off** (seeded), so its gates answer `403 MODULE_DISABLED` until it is switched on in *Tenants → modules*.

**Hardware notes.** Any of these work as a gate: Android POS / handhelds with a built-in scanner (Sunmi, Zebra DataWedge, Honeywell, Urovo, Newland, PAX — the app listens to their broadcast intents, `source: hardware`), any phone/tablet camera (`camera`), and a PC or Android device with a USB/Bluetooth HID scanner in keyboard-wedge mode (`wedge`, also the web build). Configure HID scanners to send the QR text followed by Enter, no prefix. Tickets carry `qr_code` (`TKT-YYYY-NNNNNN-<12 random>`); scanning the public `/ticket/{qr_code}` URL also works, and staff can type the ticket number (`source: manual`). Heartbeat every 30–60 s keeps the device "online" on the dashboard (battery and offline queue size included); keep a manifest fresh (`If-None-Match` is cheap) so lanes keep working when the network drops.

**Settings** (tenant `settings.gate.*`, all optional): `day_rollover_hour` 4, `early_entry_minutes` 60, `reentry_requires_exit` false, `require_staff_login` false, `offline_allowed` true, `beep` true.

## Ticket types

Contract: [`../docs/ticket_types_contract.md`](../docs/ticket_types_contract.md) (§6 = implementation notes). Every event has at least one type: creating an event (or the migration, for existing ones) adds `general` («تذكرة عامة» / General admission) at the event price, and existing tickets were linked to it (their `ticket_type` key `standard` became `general`).

| Piece | How |
|---|---|
| Data | `event_ticket_types` (key unique per event, name / description {en, ar}, category `general\|family\|vip\|child\|senior\|student\|group\|other`, `price_minor`, `admits`, `capacity` per day (null = shares the event capacity), `per_order_max`, `min_age` / `max_age`, `requires_id`, `sales_start` / `sales_end` (tenant wall-clock), `visibility` `public\|box_office`, `color`, `sort`, `is_active`). Tickets: `ticket_type_id` + `ticket_type` (key); `admits` / `entries_used` are `max_check_ins` / `check_in_count`. |
| Pricing | `CheckoutService::quoteItems()` — `items: [{ticket_type_id, quantity}]` or the legacy `quantity` + `ticket_type`; lines, per-type limits, sales windows, type and event capacity (pending gate orders hold seats), points and deposits on the total. `TicketIssuer` creates one ticket per unit with its type and admits. The legacy event `price` follows the General type; `price_from` is the cheapest public type. |
| Gate | A family ticket admitting 4 is accepted 4 times ("2 of 4" in `entry`), scan results carry the type badge, age and ID note; gates restrict by type keys (`ticket_types`, e.g. VIP Gate C = `vip`); the offline manifest carries `a` / `u`. |
| Admin | Event edit page → *Ticket types* card: add from templates (General, Family, VIP, Child, Senior, Student) or blank, edit in a modal (all fields, AR/EN, colour, active), drag to sort; a type with sales can be deactivated but not deleted. Event page → *Sales by ticket type* (sold, left for the next day, revenue). JS: `public/js/admin-ticket-types.js` (SortableJS 1.15.2 from cdnjs). |
| Seed | `TicketTypesSeeder` (idempotent by event + key). |

**Seeded prices.** north-batinah *Sohar Festival 2026–27*: only **General OMR 0.500** is a verified price. **Family (admits 4, OMR 1.500), Child (under 12, OMR 0.250), Senior (60+, ID, OMR 0.250) and VIP (OMR 2.000, VIP Gate C) are SAMPLE types with PLACEHOLDER prices** for the organizer to edit or switch off before sales open. The demo tenant has a full fictional example set (VIP with its own capacity, student ID check, group of 5, gate-only door ticket, free child ticket, family ticket, a closed early-bird window).

## Gate box office

Contract: [`../docs/gate_sales_contract.md`](../docs/gate_sales_contract.md) (§8 = implementation notes); endpoints in `business-data/PLATFORM_API.md` (*Gate box office*).

| Piece | How |
|---|---|
| Flow | Gate staff (paired device + `/gate/staff/login`, permission `sell tickets`: role `staff` and tenant admins) pick an event, quantities per ticket type and the visitor's phone → `POST /api/v1/gate/sales/orders` → a `pending` `box_office_orders` row (+ `box_office_order_lines`) priced on the server, holding its seats → `payment_url` shown as a QR, sent by SMS (`SendOtpSms`, purpose `box_office`) or shared → the visitor opens `/pay/{token}`, presses «Pay» → the normal gateway flow (`/payment/redirect/{tx}`; `PAYMENT_DRIVER=fake` locally) → `PaymentFulfillmentService` (webhook / return, idempotent) issues the tickets to the buyer through `TicketIssuer`, earns their points and marks the order `paid` (`BoxOfficeService::markPaid`) → the visitor's page shows the tickets with QR codes; the gate polls the order and admits it (`/admit` → `TicketScanService`, source `box_office`). |
| Buyer | Found or created by phone (`968XXXXXXXX`, name from `buyer_name`); they sign in to the app later with the same phone + OTP and see the tickets. |
| Expiry | Orders not paid within `gate_sales.link_ttl_minutes` expire (`php artisan box-office:expire`, scheduled every minute, and lazily on every read) and release their seats; a pending gateway checkout of an expired / cancelled order is cancelled too and the hand-off refuses it. Staff (or the admin) can cancel pending orders. |
| Late capture | A payment captured after the order expired / was cancelled gets its tickets only if seats are still free (re-checked per type inside the fulfillment transaction). Otherwise the order becomes `paid_unfulfilled` (no tickets, deposits or points; red alert and sidebar badge in *Gate sales*), the visitor sees a refund page, and an admin with `refund payments` refunds it from the order (existing payment refund, then the gateway) → `refunded`. Wallet refund requests are not used: these are card payments. |
| Settings | Tenant `gate_sales.*`: `enabled` (false by default; on for both seeded tenants), `link_ttl_minutes` 30, `send_sms` true, `max_quantity` 10, `allow_future_dates` false; SMS text `messages.box_office_sms.{ar,en}` (*Admin → Branding & settings → Gate sales*). Per gate: `can_sell` (*Gates → edit → Sell tickets*; seeded on north-batinah Main Gate A, Family Gate B and the demo Hall Entrance). |
| Admin | *Gates → Gate sales*: orders with filters (date, gate, staff, status, reference / phone), totals per gate and per staff, order detail (lines, link + QR, SMS count, payment, tickets) and cancel. |

**Payment link host.** `payment_url` = `PAY_LINK_BASE_URL` (optional) or `APP_URL` + `/pay/<token>`. Phones must reach it: set `APP_URL` (or `PAY_LINK_BASE_URL`) to the server's LAN address when testing with real phones, e.g. `APP_URL=http://192.168.1.20:8002`. Outside production, while `APP_URL` is the framework default `http://localhost`, the host the gate device called is used (so a simulator using `http://127.0.0.1:8002` gets `http://127.0.0.1:8002/pay/…`).

**SMS locally.** Without iSmartSMS credentials the SMS job logs `SMS not sent` with the full box office message (never the OTP codes) to `storage/logs/laravel.log`; with `QUEUE_CONNECTION=database` run `php artisan queue:work` for the job to execute.

**Try it locally.**
```bash
php artisan gate:pair-code north-batinah A     # pair the gate app (or curl /gate/pair)
# staff login 96890000001 with the OTP, then:
curl -X POST http://127.0.0.1:8002/api/v1/gate/sales/orders -H "Authorization: Bearer <device_token>" -H 'Content-Type: application/json' -H 'Accept: application/json' \
     -d '{"event_id":1,"items":[{"ticket_type_id":1,"quantity":2}],"buyer_phone":"99112233","locale":"ar","idempotency_key":"'$(uuidgen)'"}'
# open data.order.payment_url → Pay → Simulate success
```
Only events running on the gate day are sold (the seeded Sohar Festival starts on 2026-12-22); to test earlier, switch on *Sell for later days* and pass `booking_date` / `?date=`.

## Deploy (Docker / Coolify)

Production runs as one Docker Compose resource on Coolify: `Dockerfile` (serversideup/php 8.3 FPM + NGINX, non-root, port 8080) and `docker-compose.yml` with `app` (web, migrates and caches on every deploy, health check `/up`), `worker` (`queue:work`), `scheduler` (`schedule:work`), `mysql` 8.4 and `redis` 7; uploads live in the `app-storage` volume. Step-by-step guide (domain, variables, APP_KEY, first deploy, seeding, DNS, SMTP / SPF / DKIM, Bank Muscat URLs, backups, logs, scaling, rollback): **[`docs/DEPLOY_COOLIFY.md`](docs/DEPLOY_COOLIFY.md)**. Every environment variable, with the secrets marked: [`.env.production.example`](.env.production.example).

```bash
php artisan db:seed --force                                    # once, by hand (never automatic)
php artisan platform:create-admin ops@awj.om --lock-seeded     # real super-admin; locks the seeded password123 accounts
```

The app runs on SQLite locally and MySQL 8.4 in production; the test suite passes on both (MySQL run: docs/DEPLOY_COOLIFY.md §14).

## Adding a tenant
1. **Admin panel** (super-admin) → *Tenants* → *New*: slug, names, status; branding (logo upload, colours, fonts), modules, wallet/points/refund rules, billing address, OTP text and (optionally) Bank Muscat credentials. Optionally create the tenant's first admin user.
   Or in code: `App\Models\Tenant::create(['slug' => 'acme', 'name' => ['en' => 'Acme', 'ar' => 'أكمي'], 'status' => 'active', 'settings' => [...]])` (only store values that differ from `config('tenancy.defaults')`).
2. Assign staff: users → roles inside that tenant (switch tenant first), vendors → staff.
3. Build the app with `--dart-define=TENANT=acme --dart-define=API_BASE_URL=…`; branding changes later need no rebuild (the app re-fetches `/tenant/config`, ETag cached).

## Environment variables (platform-specific)

| Variable | Default | Purpose |
|---|---|---|
| `TENANCY_DEFAULT_TENANT` | `north-batinah` | Tenant used when `X-Tenant` is absent (old app builds), and for public pages without `?tenant=`. |
| `CORS_ALLOWED_ORIGINS` | `*` | Comma-separated web origins (gate web app, visitor web) for the API and the public image paths. |
| `TRUSTED_PROXIES` | private ranges + localhost | Proxies whose `X-Forwarded-*` headers are trusted (`config/trustedproxy.php`); add Cloudflare's ranges behind its proxy. |
| `TENANCY_CONFIG_CACHE_TTL` | `300` | Seconds `/tenant/config` is cached (busted on tenant save). |
| `PAYMENT_DRIVER` | `bankmuscat` | `bankmuscat` or `fake`. |
| `PAYMENT_LINK_TTL_MINUTES` | `60` | Lifetime of the signed `payment_url`. |
| `PAY_LINK_BASE_URL` | `APP_URL` | Base of the public gate box office links `/pay/{token}` (QR / SMS). |
| `PAY_TOKEN_TTL_SECONDS` | `60` | Wallet pay-QR lifetime. |
| `BANK_MUSCAT_GATEWAY_URL`, `BANK_MUSCAT_ACCESS_CODE`, `BANK_MUSCAT_WORKING_KEY`, `BANK_MUSCAT_MERCHANT_ID` | – | Platform fallback credentials (tenants can override, encrypted in DB). |
| `BANK_MUSCAT_WEBHOOK_IPS` | empty | Optional comma-separated webhook IP allowlist. |
| `OTP_DEBUG` | `false` | Return the OTP in `send-otp` (never in production). |
| `OTP_TEST_CODE` | empty | Fixed OTP outside production. |
| `OTP_TTL_MINUTES`, `OTP_MAX_ATTEMPTS` | `5`, `5` | OTP expiry and wrong-code attempts before the code is burned. |
| `ISMARTSMS_URL`, `ISMARTSMS_USER_ID`, `ISMARTSMS_PASSWORD` | – | SMS gateway for OTP delivery. |
| `DEMO_REQUEST_TO` | `systems@awj.om` | Recipient of landing-page demo requests. |
| `DEMO_REQUEST_CC` | empty | Optional comma-separated CC list. |
| `DEMO_REQUEST_CONFIRMATION` | `true` | Send the requester a thank-you email when they gave an email address. |

Scheduler (`php artisan schedule:work` / cron `schedule:run`): `points:expire` daily 02:00, `wallet:expire-promo` daily 02:15 (promo credit older than `wallet.promo_expiry_days`, FIFO lots in `wallet_promo_lots`; no-op while the setting is null), `payments:expire-pending` hourly (cancels abandoned gateway checkouts and returns reserved points), `box-office:expire` every minute (unpaid gate box office orders expire and release their seats).

Seeding is idempotent: `php artisan db:seed` can be re-run on an already-seeded database (natural-key `updateOrCreate` / `firstOrCreate`; content edited in the admin is not overwritten). The generic terms / privacy texts are filled from the tenant's settings (name, `tenant.organization`, `tenant.support.email|phone`).

### Syncing the Arabic seed texts

Because re-seeding never overwrites existing rows, reworded Arabic seed texts reach an already-seeded database through a separate command:

```bash
php artisan content:sync-arabic --dry-run        # list every field that would change
php artisan content:sync-arabic                  # write them (one transaction per tenant)
php artisan content:sync-arabic --tenant=demo    # only one tenant
```

It reads the texts from the seeder classes (`TenantSeeder::northBatinah()/demo()`, `NorthBatinahEventsSeeder::content()`, `DemoTenantSeeder::content()`, `NorthBatinahVendorsSeeder::VENDORS`, the legal seeders' `arabicTemplate()`) and matches rows by their English natural key (tenant slug, category / map place / restaurant / location name, event and announcement title, tag + event title, vendor `name.en`, legal page title, notification title of the demo visitor). It only updates Arabic fields of existing rows (`*_ar` columns, `ar` entries of JSON columns, Arabic strings of the tenant settings): no inserts or deletes, no migrations, and gate, ticket, wallet and payment tables are never touched. Tenant config caches are cleared by the tenant save. A second run reports `0 changes`. Arabic wording follows `../docs/arabic_style_rules.md` and `../docs/arabic_glossary.md`; check with `python3 -I ../docs/tools/arabic_lint.py database/seeders`.

## Tests

```bash
php artisan test                         # whole suite (in-memory sqlite, PAYMENT_DRIVER=fake)
php artisan test tests/Feature/Platform  # tenancy, wallet, points, merchant, payments, OTP
```

Against MySQL: build the `test` target of the `Dockerfile` and run it with `DB_CONNECTION=mysql` and a separate database (docs/DEPLOY_COOLIFY.md §14). Values read back from JSON columns come back with their object keys reordered on MySQL; compare them with `assertSameJson()` (`tests/TestCase.php`).

`tests/Feature/Platform` covers tenant isolation, the tenant config/ETag, server-side pricing, fake-gateway top-ups credited once, Bank Muscat callback verification, wallet checkout + `INSUFFICIENT_BALANCE`, idempotent debits, deposits, refund requests (refundable cash only), points earn/redeem/expire/reversal, merchant charges with expired/reused/forged pay tokens, OTP throttling and owner-scoped payment endpoints. `tests/Feature/Admin*` cover the admin panel. `tests/Feature/Gate` covers pairing (expired/used/foreign codes, rate limit, artisan command), device auth and revocation, every scan result, concurrency/idempotency, re-entry rules, manifest hashing/ETag, offline sync conflicts, tenant isolation, stats/heartbeat, staff OTP login, the legacy staff endpoints and the permission-gated admin pages. `tests/Feature/Gate/BoxOffice*` cover the box office (access rules, catalog, server pricing with items, error codes, seat holds released on expiry / cancel, idempotency, the full pay-by-link flow with a replayed callback, failed / expired / cancelled links, the SMS limit, admit, tenant isolation, admin pages and settings); `tests/Feature/Platform/TicketTypesTest` and `tests/Feature/Admin/TicketTypesAdminTest` the ticket types (resources, quotes with lines, per-type rules, issuance, multi-entry scans, manifest, admin editor). `tests/Feature/Discover` covers the places/content API (shapes, drafts, scheduling, tenant isolation, `MODULE_DISABLED`, near-sort, ETag, sanitizer), the admin CRUD with uploads and permissions and the seeder; `tests/Feature/Ai/ConciergePlacesTest` the concierge tools/cards. `tests/Feature/Landing` covers the homepage (language/dir) and the demo form (storage, mails, validation, honeypot, timing, rate limit, non-JS flow).

## Security notes
- The client never sends prices; amounts are computed server-side and gateway responses are verified (decryption with the tenant key + order id + currency + exact amount).
- Payment status/confirm/cancel/show are owner-scoped; gateway return pages never change state without a verified encrypted response; payment links are signed.
- OTP: throttled per phone/IP, max attempts, stored as HMAC, never returned unless `OTP_DEBUG=true` (non-production).
- `BANK_MUSCAT_INTEGRATION.md` previously contained a real merchant id / access code; they were redacted but remain in git history — **rotate them with Bank Muscat**.
