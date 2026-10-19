# Platform API (v1) — tenants, wallet, points, merchant, checkout

Base URL `/api/v1`. Contract: `../../docs/platform_contract.md` (§5–§9, §12).
The older endpoints (events, categories, restaurants, map, announcements,
notifications, favorites, legal) are documented in `API_DOCUMENTATION.md`;
they are unchanged except that **every request is now scoped to a tenant**.

## Common

| Header | |
|---|---|
| `X-Tenant: <slug>` | Tenant (e.g. `north-batinah`, `demo`). Missing → default tenant. Unknown/inactive → `404 {code: TENANT_NOT_FOUND}`. |
| `Authorization: Bearer <token>` | Sanctum token from `/auth/verify-otp` (users are global; the same token works for every tenant). |
| `Accept-Language: ar\|en` | Language of transaction/points titles. |

Money: integer minor units (`amount_minor: 1500`) plus a formatted string
(`amount: "1.500"`, tenant `currency_decimals`). Every money-moving POST takes
an `idempotency_key` (client UUID): replaying it returns the original result.

Error envelope: `{"success": false, "message": "...", "code": "INSUFFICIENT_BALANCE", "errors"?: {...}, "data"?: {...}}`.

Datetimes: every datetime is ISO 8601 **with an explicit offset in the tenant
time zone** (`tenant.timezone`, e.g. `2026-10-18T18:00:00+04:00` for
Asia/Muscat) — in resources and in models returned as is (announcements,
notifications…). Event `start_date`/`end_date` and announcement
`start_datetime`/`end_datetime` are the wall-clock times the organizer typed,
in the tenant zone. Date-only values (`booking_date`) stay `YYYY-MM-DD`;
`valid_from` derived from a booking date is that day's 00:00 in the tenant zone.
Parse with offset (Dart `DateTime.parse(...).toLocal()`).

## Tenant config

`GET /tenant/config` — public; `ETag` + `Cache-Control: public, max-age=300`; send `If-None-Match` to get `304`.
Returns the §5 document (+ §5a `home_blocks`, `quick_actions`, `merchant`, `branding.logo_needs_chip`: `true|false|null` — null = app decides, `wallet.promo_expiry_days`: `int|null`). Legacy `GET /config`, `/config/theme|branding|features|status|{key}` and `/app-settings[/{key}]` are derived from the tenant and expose public settings only.

## Auth (OTP)

| | |
|---|---|
| `POST /auth/send-otp {phone_number}` | Throttled: 1 per 2 min and 5/h per phone, 20/h per IP → `429 TOO_MANY_REQUESTS`. Response has `otp` only when `OTP_DEBUG=true` (non-production). SMS text = tenant `messages.otp_sms`. |
| `POST /auth/verify-otp {phone_number, otp}` | Throttled 10/10 min per phone. Wrong code → `401 OTP_INVALID` (`data.attempts_remaining`); after 5 wrong codes → `429 OTP_ATTEMPTS_EXCEEDED` (request a new code). |
| `DELETE /auth/delete-account` | Anonymises the account; `409 WALLET_NOT_EMPTY` while any wallet holds money. |

## Checkout (§8)

`GET /payments/quote?event_id=12&booking_date=2026-10-20&quantity=2&ticket_type=vip&points_to_redeem=200`
```json
{"success":true,"data":{"event_id":12,"quantity":2,"ticket_type":"vip","unit_price_minor":7250,"unit_price":"7.250",
 "subtotal_minor":14500,"subtotal":"14.500","points_to_redeem":200,"points_discount_minor":2000,"points_discount":"2.000",
 "deposit_minor":1000,"deposit":"1.000","total_minor":13500,"total":"13.500","currency":"OMR"}}
```

`POST /payments/initialize` (auth)
```json
{"event_id":12,"booking_date":"2026-10-20","quantity":2,"ticket_type":"standard","method":"wallet","points_to_redeem":0,"idempotency_key":"7c3e…"}
```
- Any `amount` sent by the client is ignored. `payable_id` is accepted as an alias of `event_id`.
- `method=gateway` → `201 {"transaction_id":"TXN…","status":"pending","payment_url":"https://…/payment/redirect/TXN…?expires=…&signature=…","checkout_url":"…","redirect_required":true,"data":{…}}`. Open `payment_url` in a web view, then poll `GET /payments/status/{transaction_id}`.
- `method=wallet` → `201 {"status":"completed","transaction_id":"TXN…","data":{"tickets":[{ "id":1,"ticket_number":"TKT-2026-000123","qr_code":"TKT-2026-000123-8KQ2…", … }]}}` or `422 INSUFFICIENT_BALANCE` / `422 SOLD_OUT` / `404 EVENT_NOT_FOUND` / `422 INSUFFICIENT_POINTS`.

Owner-scoped (404 for other users): `POST /payments/confirm {transaction_id}`, `GET /payments/status/{transaction_id}`, `GET /payments/{id}`, `GET /payments/history`, `POST /payments/cancel {transaction_id}` (pending only).
`POST /payments/webhook` — Bank Muscat server callback (`encResp`), verified by decryption with the tenant key.

## Wallet (§6, module `wallet`)

| Method | Path | Notes |
|---|---|---|
| GET | `/wallet` | `{id,status,currency,balance_minor,balance,available_minor,available,held_deposits_minor,held_deposits,cash_minor,cash,promo_minor,promo,refundable_minor,refundable,points:{balance,tier_code,next_tier_code,points_to_next_tier}}` |
| GET | `/wallet/transactions?type=&page=` | Paginated ledger: `{id,uuid,type,direction,amount_minor,amount,balance_after_minor,balance_after,available_after_minor,bucket,title,description,reference:{type,id},status,created_at,expires_at,meta}`. `expires_at` is set on promo credits when the tenant has `wallet.promo_expiry_days` (else null). Expired promo shows as an `adjustment` debit with `meta.reason = "promo_expired"`. |
| POST | `/wallet/topups` | `{amount_minor, idempotency_key}` → `{topup_id,transaction_id,payment_url,status:"pending",amount_minor,amount,bonus_minor,bonus}`. Limits: `wallet.min_topup_minor`, `max_topup_minor`, `max_balance_minor` → `422 LIMIT_EXCEEDED`. |
| GET | `/wallet/topups/{id}` | `{topup_id,status: pending\|completed\|failed\|cancelled, amount_minor, amount, bonus_minor}` |
| POST | `/wallet/pay-token` | `{token:"pt1.…", expires_at, expires_in:60}` — render as QR. |
| GET | `/wallet/deposits` | `[{id,type,label,amount_minor,amount,status: held\|released\|forfeited,vendor_name,created_at,released_at}]` |
| GET | `/wallet/refund-requests` | `[{id,amount_minor,amount,fee_minor,payout_minor,method,status,reason,admin_note,created_at}]` |
| POST | `/wallet/refund-requests` | `{amount_minor?, method: original_payment\|bank_transfer, reason?, bank_iban?, bank_name?, idempotency_key}` — only refundable cash (cash − held deposits); omit amount for the full refundable balance. `422 REFUND_NOT_ALLOWED` when disabled, below minimum, above refundable, outside the window. |

Buckets: top-ups are **cash**; points redemption, top-up bonuses and admin goodwill are **promo** (spent first, never refundable).

## Points (§7, module `points`)

| | |
|---|---|
| `GET /points` | `{balance,lifetime_earned,label,tier:{code,name,multiplier,color,min_points},next_tier,points_to_next_tier,redeem_value_minor_per_point,min_redeem_points,expiring:[{points,expires_at}]}` |
| `GET /points/history?page=` | `[{id,type: earn\|redeem\|expire\|adjust,points,balance_after,title,reference,created_at,expires_at}]` |
| `POST /points/redeem {points, idempotency_key}` | `{points_redeemed,credited_minor,credited,wallet_balance_minor,wallet_balance,points_balance}` (promo credit). `422 INSUFFICIENT_POINTS` / `LIMIT_EXCEEDED` (below `min_redeem_points`). |

## Merchant / staff (§9)

Requires role `vendor-staff` + vendor membership (header `X-Vendor-Id` to pick a vendor), or gate `staff` / tenant admin. Module `vendors` or `deposits`.

| | |
|---|---|
| `GET /merchant/me` | `{vendor:{id,name,logo_url}\|null, role, permissions:[…], vendors:[{id,name}]}` |
| `POST /merchant/charge {pay_token, amount_minor, description?, idempotency_key}` | `{transaction_id,amount_minor,amount,customer_display_name:"Fatma B.",status}`. `410 PAY_TOKEN_EXPIRED` (expired or already used), `422 PAY_TOKEN_INVALID`, `422 INSUFFICIENT_BALANCE`, `423 WALLET_FROZEN`. Retrying with the same key returns the original charge. |
| `POST /merchant/deposits {pay_token, type: wristband\|cup\|locker\|equipment\|other, amount_minor, label?, idempotency_key}` | Deposit row (`status: held`). |
| `POST /merchant/deposits/{id}/release {forfeit?: bool}` | Deposit row (`released` / `forfeited`). |
| `GET /merchant/transactions?date=YYYY-MM-DD` | `{date,vendor,transactions:[…],totals:{count,sales_minor,sales,refunds_minor,net_minor,net}}` |

Gate staff (`staff` role or `validate tickets` permission in the tenant, module `staff_scanner`): `POST /tickets/validate {qr_code}`, `POST /tickets/{id}/mark-used`, `GET /tickets/validation-history`. If `points.earn_on_checkin` is on, the first check-in awards `points.checkin_points`.

## Local testing with the fake gateway
Set `PAYMENT_DRIVER=fake`; `payment_url` then shows *Simulate success / failure / Cancel* buttons and runs the same fulfillment as the real webhook.

## Browser pages (web view)
- `/payment/success|cancel` render localized result pages (`?lang=ar|en`). Problems are passed to the view as a code (`not_pending`, `not_completed`, `no_response`, `verification_failed`, `payment_failed`, `server_error`; shown in `data-error-code`), mapped to ar/en text by `payment/error.blade.php`. The success page lists the tickets issued (links to `/ticket/{qr_code}`) and the points earned.
- `/ticket/{qr_code}` (public; the QR is the secret) shows the ticket with its status — `active` (valid), `used` (with time), `cancelled` / `refunded` / `expired` (QR dimmed, "not valid for entry"); unknown codes are 404.

## AI Experience (docs/ai_and_gate_contract.md §A)

Auth optional: a Sanctum bearer token, or for guests the app-generated opaque `X-Ai-Guest: <8–128 chars [A-Za-z0-9_-:.]>` header (stored only as a hash; required when not signed in, else `422 VALIDATION_ERROR`). Errors: `403 AI_DISABLED` (module `ai_assistant` / `ai_plan` off), `429 AI_RATE_LIMITED` (`data.retry_after` seconds, `Retry-After` header), `429 AI_BUDGET_EXCEEDED` (tenant monthly token budget used up), `503 AI_UNAVAILABLE` (OpenAI down / not configured). Request validation errors (`422`) and unknown ids (`404 NOT_FOUND`) are always JSON.

| | |
|---|---|
| `POST /ai/chat {message (≤1000), locale: ar\|en, conversation_id?, context?: {event_id?, screen?}}` | `Accept: application/json` → `{conversation_id, message: {id, text, cards: [...], suggestions: [...]}}`. Any other `Accept` (e.g. `text/event-stream`) → SSE: `event: delta` `{text}` (many), then `event: card` (one per card), then `event: done` `{conversation_id, message_id, suggestions}`; on failure `event: error` `{code, message}`. Refusals before streaming (disabled / rate / budget) come as a one-event SSE body with the matching HTTP status. Cards: `event {type,id,title,start,end,venue,price_minor,price,image_url,is_free}`, `location {type,id,name,lat,lng,category}`, `ticket {type,id,event_title,date,status}` (own tickets only), `action {type,action: open_event\|buy_ticket\|open_map\|open_wallet\|top_up\|open_refunds\|open_plan,label,params:{event_id?}}`, plus `place` / `content` cards when those modules are on (see *Places to visit & content*). Limits: 20 msgs/10 min signed in, 8/10 min per guest key (+60/10 min per IP). |
| `POST /ai/messages/{id}/feedback {rating: up\|down, comment?}` | `{id, rating}` — only for assistant messages of your own conversations. |
| `GET /ai/conversations` | Paginated `[{id, title, locale, last_message, created_at, updated_at}]` (yours; guests by `X-Ai-Guest`). Conversations expire 30 days after the last message. |
| `GET /ai/conversations/{id}` | `{id, title, locale, created_at, updated_at, messages: [{id, role: user\|assistant, text, cards, suggestions, feedback, created_at}]}` |
| `DELETE /ai/conversations/{id}` | `{success: true}` (messages deleted too). |
| `POST /ai/plan {date, start_time?, end_time?, party: {adults, kids?, kids_ages?}, interests?: [..], budget_minor?, notes?, locale}` | `{plan: {id, title, summary, date, items: [{start, end, kind: event\|food\|break\|travel\|activity, event_id, location_id, title, note, cost_minor}], total_cost_minor, total_cost, currency, tips, warnings, dropped_items, created_at}}`. Event items always reference a real event of the tenant inside its hours that day; `cost_minor` = server price × people for events, `null` for other items. 6/10 min signed in, 3/10 min guest. |
| `GET /ai/plans/{id}` | Same `{plan}` (shareable inside the tenant). |

Admin (session + CSRF, JSON): `POST /admin/ai/copilot/event-description` (`manage events`), `POST /admin/ai/copilot/translate {from, to, fields: {title?, description?, …}}` and `POST /admin/ai/copilot/shorten {text, locale, max_chars?}` (`manage events` or `manage announcements`), `POST /admin/ai/copilot/announcement {brief, type?}` (`manage announcements`), `GET /admin/ai/briefing` + `POST /admin/ai/briefing/regenerate` (`view payments`), `GET /admin/gates/insights?date=&refresh=` (`manage gates`, response shape in the contract §A6).

## Places to visit & content (docs/places_content_contract.md)

Public (auth optional), tenant from `X-Tenant`, language from `Accept-Language`. Modules: `/places/*` needs `places`, `/content/*` needs `content` (else `403 MODULE_DISABLED`). Only `published` rows (and `published_at` ≤ now) of active categories / collections; drafts and other tenants' rows → `404 NOT_FOUND`. Every localized field comes as `{en, ar}` **and** `<field>_text` (request language, falling back to the other one). Images: `{id, url, thumb_url, width, height}` (thumb = 640×420 webp crop; logos 480×480 uncropped) or `null`. HTML bodies (`description`, `body`) are sanitized (p, br, strong/b, em/i, u, ul/ol/li, h3/h4, blockquote, http/https/mailto/tel links). Lists are paginated like events: `pagination: {current_page, last_page, per_page, total}`.

| | |
|---|---|
| `GET /places/categories` | `[{id, key, name, name_text, icon, color, places_count}]` (active categories, `sort` order; `places_count` = published places). |
| `GET /places?category=&q=&featured=0\|1&near=lat,lng&page=&per_page=` (≤100, default 20) | `[{id, slug, name, name_text, summary, summary_text, category: {id, key, name, name_text, icon, color}\|null, icon, color, latitude, longitude, cover, wilayat, wilayat_text, featured, entry_fee_minor, entry_fee, currency}]`. `icon` = the place override or the category icon; `entry_fee_minor` `null` = unknown, `0` = free. `category` = key or id. `q` searches name/summary/wilayat/address in both languages. Default order: featured first, then `sort`. `near` → sorted by distance (haversine) with `distance_m`. |
| `GET /places/map` | `[{id, name, name_text, latitude, longitude, icon, color, category_key, featured}]` — all published places, no pagination. `ETag` (varies with `Accept-Language`) → `If-None-Match` gives `304`. |
| `GET /places/{id or slug}` | List fields + `description`, `description_text` (html), `address(_text)`, `opening_hours(_text)` (free text, `null` = not published), `phone`, `link_url`, `link_label(_text)` (default "More info" / «مزيد من المعلومات» when a link is set), `gallery: [image]`, `events: [{id, title, title_text, start_date, end_date, image_url, price_minor, price, is_free}]` (upcoming active events linked to the place, max 10), `share_url` (`/discover/places/{slug}?tenant=&lang=` public web page), `published_at`, `updated_at`. |
| `GET /content/collections` | `[{id, key, name, name_text, description, description_text, icon, layout: list\|grid\|logos\|cards, items_count, show_in_discover, sort}]` (active collections; an empty one has `items_count: 0`). |
| `GET /content/collections/{key}/items?q=&tag=&page=&per_page=` (≤100, default 30) | `[{id, slug, collection_key, title, title_text, subtitle, subtitle_text, icon, image, link_url, link_label, link_label_text, featured, tags}]`, featured first, then `sort`. |
| `GET /content/items/{id}` | Item + `body`, `body_text` (html), `phone`, `email`, `place: {id, slug, name, name_text, icon, color, latitude, longitude, cover}\|null` (published places only), `latitude`/`longitude` (the place's, else the item's own, else `null`), `gallery`, `collection: {id, key, name, name_text, layout}`, `updated_at`. |
| `GET /content/pages` | `[{id, slug, title, title_text, icon, cover, show_in_more, sort}]` |
| `GET /content/pages/{slug}` | Page + `body`, `body_text` (html), `updated_at`. |

Tenant config: modules `places`, `content` (default **off**; on for both seeded tenants), navigation tab `discover` (kept only when `places` or `content` is on), home blocks `places_featured`, `places_map_teaser`, `collection:<key>`, quick action `places`.

Concierge (`POST /ai/chat`): tools `find_places(query?, category?, near?)` and `search_content(query?, collection?)` exist only while the module is on; extra cards `place {type, id, name, category, category_key, icon, color, cover_url, latitude, longitude}` and `content {type, id, collection_key, title, image_url, link_url}` (rebuilt from the DB; published rows of the tenant only).

Admin (session, permissions `manage places` / `manage content`; tenant admins and `content-manager`): `/admin/places` (+ `create`, `{id}/edit`, `map`, `POST reorder {ids}`, `POST {id}/featured`), `/admin/place-categories` (+ `POST reorder`), `/admin/content/collections` (+ `{collection}/items`, `POST reorder`), `/admin/content/pages`. Copilot: `POST /admin/ai/copilot/content-description {kind: place|item|page, title?, title_ar?, category?, collection?, wilayat?, address?, subtitle?, opening_hours?, notes?}` → `{summary_ar, summary, description_ar, description}`; `translate` / `shorten` also accept `manage places` / `manage content` and keep HTML tags.

## Gate scanner (docs/ai_and_gate_contract.md §B)

Prefix `/api/v1/gate`, module `staff_scanner`. Auth = **device token**: `Authorization: Bearer <device_token>` (a Sanctum token of the paired `GateDevice`, ability `gate`). User tokens are refused here (`401 UNAUTHENTICATED`) and device tokens are refused on every user route. The device's tenant is the request tenant (`X-Tenant` optional; a different slug → `403 TENANT_MISMATCH`). Revoked device → `401 DEVICE_REVOKED` (pair again). Dates/times: ISO 8601 with the tenant offset; "today" is the **gate day** (`gate.day_rollover_hour`, default 4 → the day runs 04:00–04:00 local).

| | |
|---|---|
| `POST /pair {pairing_code, device: {name, device_type: android_pos\|handheld\|phone\|tablet\|web, model, platform, app_version}}` (no auth, 10/min per IP) | `201 {device_token, device: {id, name}, gate: {id, name: {en, ar}, code, event_id, direction: entry\|exit\|both, allow_reentry, ticket_types, active}, tenant: {slug, name}}`. Code case/spaces ignored. `422 PAIRING_CODE_INVALID`, `410 PAIRING_CODE_EXPIRED` (10 min), `410 PAIRING_CODE_USED`. |
| `GET /session` | `{device, gate, staff: {id,name}\|null, tenant: {slug,name,timezone}, date, events_today: [{id, title, title_i18n, start, end}], settings: {allow_reentry, offline_allowed, beep, require_staff_login, direction}, manifest_version, server_time}` |
| `POST /staff/login {phone, otp}` | Call `POST /auth/send-otp {phone_number}` first (same OTP flow; no user token is issued). `{staff: {id, name}}` — bound to the device until logout; scans are attributed to them. `401 OTP_INVALID`, `429 OTP_ATTEMPTS_EXCEEDED`, `403 NOT_STAFF` (needs admin/staff role or the `validate tickets` permission in the tenant). |
| `POST /staff/logout` | `{ok: true}` |
| `POST /scan {code, source: camera\|hardware\|wedge\|manual, direction?: in\|out, scanned_at?, idempotency_key}` (240/min per device) | `{result, admit, direction: in\|out, message: {en, ar}, ticket: {number, holder_name, type, event_id, event_title, valid_date, used_at, quantity, quantity_label, check_in_count, max_check_ins}\|null, scan_id, replay, counts: {in_today, out_today}}`. `code` = the ticket QR (`qr_code`; the public `/ticket/{qr_code}` URL is accepted too); with `source=manual` the ticket number is accepted. Same key again → same result (`replay: true`); key of another device → `409 IDEMPOTENCY_CONFLICT`. `409 GATE_INACTIVE` (gate closed), `403 STAFF_LOGIN_REQUIRED` (tenant setting `gate.require_staff_login`). |
| `GET /manifest?date=` | `{version, generated_at, date, salt, gate_id, count, tickets: [{h, s: active\|used\|cancelled\|refunded, t, e, d}]}` with `ETag: "<version>"`; send `If-None-Match` → `304`. `h = sha256(salt + qr_code)` (hex); only tickets valid that day for the gate's event (or all events). |
| `POST /sync {scans: [{code_hash, result_local, source, direction, scanned_at, idempotency_key}]}` (1–500, 30/min) | `{results: [{idempotency_key, result, conflict}] (same order), counts}`. Processed oldest first; re-sending is harmless. |
| `GET /stats?date=` | `{date, scope: gate\|device, gate_id, in_today, out_today, rejects_today, by_hour: [{hour, in, out, rejects}], recent: [{id, result, direction, source, offline, conflict, ticket_number, ticket_type, scanned_at}]}` (20 most recent) |
| `POST /heartbeat {app_version?, battery?, queue_size?, scanner_source?}` | `{server_time, manifest_version, device_status}` — send every ~30–60 s; drives "online" (≤ 2 min) on the admin devices page. |

**Results**: `valid` (admit; exits are always `valid` with `direction: out`), `reentry_ok`, `already_used`, `invalid`, `wrong_event` (gate restricted to another event), `wrong_date` (other day, expired ticket or ended event), `not_yet_open` (before event start − `gate.early_entry_minutes`, default 60), `cancelled`, `refunded`, `wrong_gate` (gate accepts other ticket types). Messages always come in both languages; `event_title` / `quantity_label` follow `Accept-Language`.

**Offline**: download the manifest (re-check with `If-None-Match`), hash each scanned code as `sha256(salt + code)`, admit `active` entries whose `d` is today (respect `allow_reentry` locally), mark them used locally and queue `{code_hash, result_local, …}`; `POST /sync` when back online. The server records rejections as they happened; an offline admission it would not have made (typically: admitted elsewhere first) comes back with `conflict: true`.

**Tenant settings** (`gate.*`, all optional): `day_rollover_hour` (4), `early_entry_minutes` (60), `reentry_requires_exit` (false — when true, re-entry needs an exit scan first), `require_staff_login` (false), `offline_allowed` (true), `beep` (true).

Legacy staff screen (`/tickets/validate`, `/tickets/{id}/mark-used`) runs on the same rules (`App\Services\Gate\TicketScanService`) and keeps its response shape; it adds `result` + `messages {en, ar}` to errors and two new `error_code`s: `WRONG_DATE` (ticket booked for a later day — earlier builds accepted it) and `NOT_YET_OPEN`. Rejections and admissions are logged in `gate_scans` without a gate/device.
