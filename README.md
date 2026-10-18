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

`GET /` is the product homepage (`LandingController@index`, `resources/views/landing/index.blade.php`, assets in `public/landing/` with `?v=filemtime` cache busting; no build step). It is bilingual: the server picks the first-paint language from `?lang=ar|en`, then the `mawsim_lang` cookie (also written by the page's EN/AR toggle), then `Accept-Language`, and sets `<html lang dir>` accordingly; the JS dictionary in `landing.js` renders the copy. Staff reach the admin through the footer's "Staff sign in" link (`/admin/login`).

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

## Adding a tenant
1. **Admin panel** (super-admin) → *Tenants* → *New*: slug, names, status; branding (logo upload, colours, fonts), modules, wallet/points/refund rules, billing address, OTP text and (optionally) Bank Muscat credentials. Optionally create the tenant's first admin user.
   Or in code: `App\Models\Tenant::create(['slug' => 'acme', 'name' => ['en' => 'Acme', 'ar' => 'أكمي'], 'status' => 'active', 'settings' => [...]])` (only store values that differ from `config('tenancy.defaults')`).
2. Assign staff: users → roles inside that tenant (switch tenant first), vendors → staff.
3. Build the app with `--dart-define=TENANT=acme --dart-define=API_BASE_URL=…`; branding changes later need no rebuild (the app re-fetches `/tenant/config`, ETag cached).

## Environment variables (platform-specific)

| Variable | Default | Purpose |
|---|---|---|
| `TENANCY_DEFAULT_TENANT` | `north-batinah` | Tenant used when `X-Tenant` is absent (old app builds), and for public pages without `?tenant=`. |
| `TENANCY_CONFIG_CACHE_TTL` | `300` | Seconds `/tenant/config` is cached (busted on tenant save). |
| `PAYMENT_DRIVER` | `bankmuscat` | `bankmuscat` or `fake`. |
| `PAYMENT_LINK_TTL_MINUTES` | `60` | Lifetime of the signed `payment_url`. |
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

Scheduler (`php artisan schedule:work` / cron `schedule:run`): `points:expire` daily 02:00, `wallet:expire-promo` daily 02:15 (promo credit older than `wallet.promo_expiry_days`, FIFO lots in `wallet_promo_lots`; no-op while the setting is null), `payments:expire-pending` hourly (cancels abandoned gateway checkouts and returns reserved points).

Seeding is idempotent: `php artisan db:seed` can be re-run on an already-seeded database (natural-key `updateOrCreate` / `firstOrCreate`; content edited in the admin is not overwritten). The generic terms / privacy texts are filled from the tenant's settings (name, `tenant.organization`, `tenant.support.email|phone`).

## Tests

```bash
php artisan test                         # whole suite (in-memory sqlite, PAYMENT_DRIVER=fake)
php artisan test tests/Feature/Platform  # tenancy, wallet, points, merchant, payments, OTP
```

`tests/Feature/Platform` covers tenant isolation, the tenant config/ETag, server-side pricing, fake-gateway top-ups credited once, Bank Muscat callback verification, wallet checkout + `INSUFFICIENT_BALANCE`, idempotent debits, deposits, refund requests (refundable cash only), points earn/redeem/expire/reversal, merchant charges with expired/reused/forged pay tokens, OTP throttling and owner-scoped payment endpoints. `tests/Feature/Admin*` cover the admin panel. `tests/Feature/Landing` covers the homepage (language/dir) and the demo form (storage, mails, validation, honeypot, timing, rate limit, non-JS flow).

## Security notes
- The client never sends prices; amounts are computed server-side and gateway responses are verified (decryption with the tenant key + order id + currency + exact amount).
- Payment status/confirm/cancel/show are owner-scoped; gateway return pages never change state without a verified encrypted response; payment links are signed.
- OTP: throttled per phone/IP, max attempts, stored as HMAC, never returned unless `OTP_DEBUG=true` (non-production).
- `BANK_MUSCAT_INTEGRATION.md` previously contained a real merchant id / access code; they were redacted but remain in git history — **rotate them with Bank Muscat**.
