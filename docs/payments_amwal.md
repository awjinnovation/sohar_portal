# Amwal Pay (SmartBox)

Card payments can run through **Amwal Pay**'s SmartBox instead of, or next
to, Thawani, per tenant (*Admin → Tenants → Payment gateway*, §5). The flow keeps the
guarantees of the Thawani integration ([payments_thawani.md](payments_thawani.md)):
retried and recorded gateway calls, a circuit breaker, exactly-once
fulfilment, amount checks, late-capture and double-charge detection, and safe
refunds.

Code: `app/Services/Payments/AmwalCheckout.php` (flow),
`app/Services/Payments/Gateway/{AmwalGateway,AmwalConfig}.php` (hashing, API),
`resources/views/payment/amwal.blade.php` (the SmartBox page),
`PaymentWebhookController::amwal()` (cloud notifications). The jobs, the
scheduler (`payments:reconcile`) and the admin pages are shared with Thawani
through `GatewayCheckouts`. Tests: `tests/Feature/Platform/AmwalGatewayTest.php`.

Reference: Amwal's developer pages (SmartBox implementation, secure hash,
merchant cloud notification, Get Transaction by Merchant Reference, refund)
and Amwal's own WooCommerce plugin (`amwalpay-for-woocommerce` 1.0.23), whose
field names and formats this follows where the pages are unclear.

## 1. Flow

```
App ── POST /payments/initialize | /wallet/topups ──► payment (pending, gateway amwal) + signed payment_url
App opens payment_url ──► /payment/redirect/{tx} ──► our page: SmartBox.js + server-signed config
                          (each open = a new attempt, merchant reference {tx}_{n})
Visitor pays in SmartBox ──► completeCallback ──► /payment/success?transaction_id&t&…callback fields
                              (secure hash checked; else Amwal API asked) ──► result page
Amwal ──► POST /api/v1/payments/webhooks/amwal/{tenant} (cloud notification, SecureHash in body) ──► job
App polls /payments/status | /wallet/topups/{id} ──► Amwal API (at most every 8 s per payment)
Scheduler, every minute ──► payments:reconcile ──► Amwal API for due payments
```

A payment is completed when either:

- the **Amwal API** (`POST Transaction/GetByMerchantReference`) returns an
  approved (`responseCode "00"`) purchase for one of its references, or
- a payload **signed with the merchant's secure key** says so: the SmartBox
  completeCallback (relayed by our page, accepted only with our return token)
  or a cloud notification.

Either way the paid amount and currency (512 = OMR) must equal the payment's,
otherwise it is flagged **needs review** (`amount_mismatch`) and nothing is
issued. A declined callback / notification never fails a payment (the
visitor can try again); the API is asked instead.

Amwal cannot cancel or expire a SmartBox attempt. Attempts stay `open`, are
watched for a late capture, and are closed (`expired`) after
`AMWAL_SESSION_TTL_MINUTES` (120). Cancelling (in the app, or *Cancel* on our
page) asks Amwal first, then cancels the payment; a later payment of a still
open SmartBox completes it (`late_capture`); a second paid attempt of a paid
payment is flagged `duplicate_capture`.

## 2. Secure hash

Fields sorted by name, `key=value` joined with `&` (no URL encoding; null,
`"null"` and `"undefined"` are empty), HMAC-SHA256 with the **hex-decoded**
secure key, upper-case hex (`AmwalGateway::sign()`).

| Message | Fields |
|---|---|
| SmartBox config (`SecureHash`) | `Amount` (= `AmountTrxn`, "2.500"), `CurrencyId` (512), `MerchantId`, `MerchantReference`, `RequestDateTime` (= `TrxDateTime`, UTC `yyyyMMddHHmmss`), `SessionToken` (empty), `TerminalId` |
| completeCallback (`secureHashValue`) | `amount`, `currencyId`, `customerId`, `customerTokenId`, `merchantId`*, `merchantReference`, `responseCode`, `terminalId`*, `transactionId`, `transactionTime` — *ours, not sent by SmartBox |
| Cloud notification (`SecureHash`) | `Amount`, `AuthorizationDateTime`, `CurrencyId`, `DateTimeLocalTrxn`, `MerchantId`, `MerchantReference`, `Message`, `PaidThrough`, `ResponseCode`, `SystemReference`, `TerminalId`, `TxnType` |
| API requests (`secureHashValue`) | every other field of the JSON body |

## 3. Amwal API used

| Call | Use |
|---|---|
| `POST Transaction/GetByMerchantReference` `{merchantId, merchantReference, requestDateTime}` | Status of an attempt: `data[]` purchases (`id` GUID, `amount` in OMR, `responseCode`, `isRefunded`, `transactionActions`). Also *Test connection* (an unknown reference). |
| `POST Execute/Void` / `Execute/Refund` `{amount, currencyCode, merchantId, requestDateTime, terminalId, transactionId (new GUID), transactionIdentifierType 2, transactionIdentifierValue (GUID)}` | Full refund: void while `transactionActions.canVoid`, else refund. |

Hosts: UAT `https://test.amwalpg.com:14443` (API) and
`https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1`; production
`https://webhook.amwalpg.com` and
`https://checkout.amwalpg.com/js/SmartBox.js?v=1.1`. Note the non-standard
ports in UAT: the server must be allowed to reach port 14443 outbound.

Retries, circuit breaker, records (`payment_gateway_calls`,
`payment_webhook_events`, `payment_events`, `payment_refunds`) and refund
safety (money out is never resent after an unclear answer; it goes to
*needs review*) are exactly as for Thawani (payments_thawani.md §3–§4, §7).

## 4. Setup (UAT)

1. *Admin → Tenants → (tenant) → Payment gateway*: Amwal environment
   **UAT**, then under *Amwal Pay · SmartBox*: Merchant ID (MID),
   Terminal ID (TID) and **Secure hash key** (stored encrypted, write-only).
   The merchant portal username / password are not needed here.
2. *Test connection* (next to the Amwal fields): a signed, read-only inquiry.
3. Send Amwal the **notification URL** shown on that page
   (`/api/v1/payments/webhooks/amwal/<tenant-slug>`) to register as the
   merchant's cloud notification URL. Payments complete without it (callback,
   polls, reconciler) — it covers visitors who close the page before returning.
4. Make a payment from the app / booking page and pay with an Amwal UAT test
   card; check *Admin → Payments → (payment)*: timeline, attempts, calls.
5. Go-live: production MID / TID / key from Amwal, environment
   **Production**, and confirm with Amwal that *Get Transaction by Merchant
   Reference* and refunds are enabled on the production profile.

Platform fallback (`.env`): `AMWAL_MODE`, `AMWAL_MERCHANT_ID`,
`AMWAL_TERMINAL_ID`, `AMWAL_SECURE_KEY`; optional `AMWAL_SESSION_TTL_MINUTES`,
`AMWAL_VIEW_TYPE` (1 popup, 2 full screen), `AMWAL_CONTACT_INFO_TYPE`
(1 all … 4 none).

## 5. Gateway test page, switching gateways on and off

*Admin → Payments → Gateway test* (platform admins, `can:manage tenants`)
shows both gateways for the current tenant (configured or not, UAT /
production, MID / TID, notification URL, *Test connection*, whether it is on
for visitors) and makes a **real small charge** (0.100–5 OMR) through the
gateway you pick — Amwal or Thawani — **whether or not it is switched on for
visitors**. The payment page returns to the test page, which shows the
result; *Check now* asks the gateway and *Refund* voids / refunds it (Amwal
picks void on the same day). Test payments (`payment_type = gateway_test`)
issue no tickets or wallet credit and are left out of dashboard revenue; they
use the same pages, callbacks, notifications, reconciler and refund code as
real payments.

**Switching.** *Admin → Tenants → (tenant) → Payment gateway*:

| Field | Setting | Meaning |
|---|---|---|
| *بوابات الدفع المفعّلة للزوار* — a checkbox per gateway (ثواني, أموال) | `payments.gateways` (list) | The card gateways new payments may use. |
| *بوابة الدفع الافتراضية* | `payments.driver` (blank = `PAYMENT_DRIVER`) | Used when the visitor / app names none. Must be one of those ticked. |

Rules (checked on save, nothing is stored when refused): at least one
gateway ticked; the default ticked; a gateway can only be ticked when it has
credentials (the organization's, else the platform's `.env` ones) — **refused,
not warned**, so visitors are never offered a gateway that cannot take a
payment. Choosing a default ticks it in the browser. One gateway ticked is
stored as before the setting existed (`payments.gateways` removed, only
`payments.driver`), so a tenant that never touches the checkboxes behaves
exactly as before. Bank Muscat and the local fake gateway work alone: as the
default they ignore the checkboxes.

With **two on**, the visitor chooses; with one, nobody is asked:

- App: `GET /api/v1/tenant/config` → `payments: {gateways: [{id, name: {ar, en}}] (default first), default_gateway}`;
  `POST /payments/initialize` and `POST /wallet/topups` take an optional
  `gateway` (absent = default; ignored for `method: wallet`) and answer with
  `gateway`. A gateway that is off → `422 GATEWAY_NOT_AVAILABLE` (`data:
  {gateway, available}`). A replayed `idempotency_key` returns the original
  payment whatever gateway it names. Old builds send nothing and keep working.
  The config is cached per tenant but the cache is dropped when the tenant is
  saved, so its `ETag` changes at once (apps may still hold it for the
  `max-age` of 300 s; a stale choice gets `GATEWAY_NOT_AVAILABLE`, after which
  the app should reload the config).
- Gate box office `/pay/{token}`: one button per gateway (default first),
  else the single *Pay* button. Choosing the other gateway after opening one
  cancels the first payment through its own flow (the gateway is asked first)
  and starts a new one.
- Booking page `/e/{event}` and the booking site API (`GET …/booking/events/{id}`
  → `payments.gateways` [{id, name}], empty with one gateway; checkout
  `gateway`): one button per gateway. The React site (`booking-web/`) shows
  them too once it is rebuilt; an old build keeps one button (the default).

A payment keeps the gateway it was created with, so switching a gateway off
never strands an open payment (its callback, notifications, reconciliation
and refunds still go to its own gateway).

Go-live order:

1. UAT: enter the UAT credentials, *Test connection*, then on the test page
   pay with an Amwal test card → *completed*; *Refund* → void succeeded;
   send Amwal the notification URL and check a notification arrives
   (*Payments → Gateway log*).
2. Production credentials from Amwal (and the notification URL registered on
   the production merchant), environment **Production**, *Test connection*,
   one 0.100 OMR test payment with a real card, then *Refund* it.
3. Tick **أموال** (with Thawani still ticked, visitors now choose; untick
   Thawani and make Amwal the default to move everyone to Amwal). To roll
   back, untick Amwal; payments already made on Amwal keep working.

## 6. To confirm in UAT

Not stated precisely in Amwal's pages; the code follows Amwal's plugin and
fails safe (needs review / API check) if they differ:

- the `Amount` format of cloud notifications (assumed OMR, e.g. `2.5`);
- that the notification `MerchantId` / `TerminalId` equal the configured ones;
- the `TxnType` values of refund / void notifications (only purchases are
  applied; others are recorded).
