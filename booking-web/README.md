# Booking site (booking-web)

The public page a visitor opens from an event's printed QR code: phone number,
6-digit code, choose tickets, pay by card, get the tickets with their entry QR.
No app and no account form. The same account then signs in to the mobile app with
the same phone number and finds the tickets there.

It replaces the Blade page on the backend (`/e/{event}`,
`resources/views/visitor/event.blade.php`), with the same flow, text and design,
as a static React build on **its own domain and its own Docker image**, so it can
sit behind a CDN and deploy separately from the backend.

```
 visitor ──► https://book.mawsim.awj.om/e/12        (this image: nginx, static files, CDN-cacheable)
                │  fetch (CORS)
                ▼
             https://mawsim.awj.om/api/v1/booking/events/12/…   (backend, Api\BookingController)
                │  pay
                ▼
             payment gateway ──► backend /payment/success ──► https://book.mawsim.awj.om/e/12?payment=success
```

| Path | |
|---|---|
| `/e/{event}` | The booking page (`?lang=ar` / `en`; default: the tenant's language) |
| `/` | Short help text, or a redirect to `HOME_URL` |
| `/healthz` | `ok` (container health check) |
| `/build-info.json` | Version, commit, build time |

## How it works

- **One image for every tenant.** The backend finds the tenant from the event, so
  branding (name, logo, colours, fonts), languages, phone format and currency all
  come from the API. Nothing tenant-specific is built in.
- **One image for every environment.** The backend address is read when the
  container starts (`API_BASE_URL`) and written into `index.html`; it is not
  compiled in. A restart is enough to point it elsewhere.
- **Sign-in** is a Sanctum token kept in the browser's local storage for
  `VISITOR_SESSION_DAYS` (backend, default 30). Users are global, so one sign-in
  serves every event. *Sign out* revokes the token.
- **Tickets** show the entry QR (drawn in the browser from the ticket code, error
  correction M, the same as the backend's ticket pages), and *Save ticket as
  image* draws a PNG (phones get the share sheet, so *Save Image* is one tap).
- **Strict CSP.** Scripts and styles come only from this site; theme colours are
  set through the CSSOM, so no `'unsafe-inline'` is needed.

Backend API (all under `/api/v1/booking/events/{event}`):

| | |
|---|---|
| `GET /` | Public. Event, ticket types, days, tenant branding. Cached by the backend (Redis) and sent with `Cache-Control: public, max-age=0, s-maxage=15` |
| `POST /otp`, `POST /verify` | Phone (as typed: `9123 4567`, `+968…`) → code → token |
| `GET /tickets`, `POST /checkout`, `POST /logout` | With the token |

## Develop

```bash
cd booking-web
cp .env.example .env.local          # VITE_API_BASE_URL=http://127.0.0.1:8002
npm install
npm run dev                         # http://127.0.0.1:5174/e/1
```

Run the backend with `php artisan serve --port 8002`, and add
`http://127.0.0.1:5174` to its `CORS_ALLOWED_ORIGINS` (or leave it `*`). Outside
production, `OTP_TEST_CODE=123456` in the backend gives every number that code
and sends no SMS; `PAYMENT_DRIVER=fake` gives a test payment page.

Text is in `src/i18n.ts` (Arabic per `docs/arabic_style_rules.md`; run
`python3 ../../docs/tools/arabic_lint.py src/i18n.ts index.html --summary` after
changing it). Styles: `src/styles/public.css` is a copy of the backend's
`public/css/public.css`, and `src/styles/booking.css` holds the page's own rules.
Keep both in step with the backend.

## Docker

```bash
docker build -t mawsim-booking-web booking-web
docker run --rm -p 18100:80 -e API_BASE_URL=http://127.0.0.1:8002 mawsim-booking-web
curl -i http://127.0.0.1:18100/healthz
```

Multi-stage: `node:22.22.3-alpine3.22` builds (`npm ci`, `vite build`, gzip
copies of the assets) → `nginx:1.30.6-alpine3.24-slim` serves (same nginx as the
gate / visitor web apps). The image is about 22 MB (6 MB to pull). The container refuses to
start without a valid `API_BASE_URL`.

| Runtime variable | Default | |
|---|---|---|
| `API_BASE_URL` | (required) | Backend address without `/api/v1`, e.g. `https://mawsim.awj.om` |
| `HOME_URL` | empty | Where `/` redirects (e.g. the festival's website); empty = a short help text |
| `CONTENT_SECURITY_POLICY` | built from `API_BASE_URL` | Replaces the whole CSP; `""` sends none |
| `PERMISSIONS_POLICY` | camera, microphone, location, payment off | Replaces the header |

Build argument `SOURCE_COMMIT` (Coolify can pass it) ends up in `/build-info.json`.

## Deploy on Coolify

1. **DNS:** `book.mawsim.awj.om` → the Coolify server (`A`/`AAAA`), or to the CDN (below).
2. **New resource → Application →** this repository (`awjinnovation/sohar_portal`), branch `main`.
   **Build pack: Dockerfile. Base Directory: `/booking-web`.** Dockerfile location `/Dockerfile`.
3. **Environment variables** (runtime): `API_BASE_URL=https://mawsim.awj.om`, optional `HOME_URL`.
4. **Network:** Ports Exposes `80`. **Domains:** `https://book.mawsim.awj.om`. Health check path `/healthz`, port `80`
   (the image also has a Docker `HEALTHCHECK`).
5. **Watch Paths:** `booking-web/**`, so backend-only pushes do not rebuild it. On the backend resource
   add `!booking-web/**` for the same reason.
6. **Backend resource**, then redeploy it:
   - `BOOKING_WEB_URL=https://book.mawsim.awj.om`
   - add `https://book.mawsim.awj.om` to `CORS_ALLOWED_ORIGINS`

With `BOOKING_WEB_URL` set on the backend:

- *Admin → Events → event → Booking QR* prints `https://book.mawsim.awj.om/e/{id}`;
- the old page `https://mawsim.awj.om/e/{id}` redirects there, so QR codes printed before the switch still work;
- card payments started on the booking site return to it (`?payment=success|cancelled|failed`).

Unset it to go back to the page on the backend; nothing else changes.

## Caching and CDN

| Response | `Cache-Control` | Why |
|---|---|---|
| `/assets/*` (JS, CSS: file names carry a content hash) | `public, max-age=31536000, immutable` | Never changes under the same name |
| `index.html`, `/e/{id}`, `/` | `no-cache` + `ETag` | Revalidated every time (a tiny 304), so a deploy is live at once without a purge |
| Any 4xx/5xx | `no-store` | A missing asset during a deploy must not be cached for a year |
| `/healthz`, `/build-info.json` | `no-store` | |
| API `GET /api/v1/booking/events/{id}` | `public, max-age=0, s-maxage=15, stale-while-revalidate=15` | A CDN in front of the API may keep it 15 s (`BOOKING_CDN_MAX_AGE`); availability is checked again at checkout |

With **Cloudflare**: proxy (orange cloud) `book.mawsim.awj.om`, SSL mode *Full (strict)*, and a Cache Rule
*hostname equals book.mawsim.awj.om → Eligible for cache, Edge TTL: use origin Cache-Control header*. HTML is
not cached by Cloudflare by default, which matches `no-cache`; the hashed assets are served from the edge.
No purge is needed after a deploy. Any other CDN works the same way if it honours the origin's
`Cache-Control`.

If you also put a CDN in front of the **API**: keep `CORS_ALLOWED_ORIGINS=*` on the backend (safe here:
the API uses tokens, not cookies), or make the CDN key on the `Origin` header. Otherwise a response cached
for one web app can be served to another with the wrong `Access-Control-Allow-Origin`. Add the CDN's IP
ranges to `TRUSTED_PROXIES` so rate limits see the visitor's address.

## Checks after a deploy

```bash
curl -s https://book.mawsim.awj.om/healthz                       # ok
curl -s https://book.mawsim.awj.om/build-info.json               # commit, built_at
curl -sI https://book.mawsim.awj.om/e/1 | grep -i cache-control  # no-cache
curl -s "https://mawsim.awj.om/api/v1/booking/events/1?lang=ar" -H "Origin: https://book.mawsim.awj.om" -D - -o /dev/null \
  | grep -iE "access-control-allow-origin|cache-control"         # the booking origin, s-maxage
curl -sI https://mawsim.awj.om/e/1 | grep -i location            # https://book.mawsim.awj.om/e/1
```

Then open an event's booking QR in *Admin* and scan it with a phone.
