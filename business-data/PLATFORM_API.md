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
