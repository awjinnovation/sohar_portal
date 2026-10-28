# Thawani Pay: checkout, retries, records

Card payments (wallet top-ups, tickets, gate box office links) go through
**Thawani Pay**'s hosted checkout. The integration is built for peak traffic:
every call to Thawani is retried safely, a payment is confirmed even when a
webhook is lost, and every step is recorded.

Code: `app/Services/Payments/ThawaniCheckout.php` (flow),
`app/Services/Payments/Gateway/*` (HTTP, retries, circuit breaker, Thawani API),
`app/Jobs/{ProcessPaymentWebhook,ReconcilePayment,ProcessGatewayRefund}.php`,
`routes/console.php` (`payments:reconcile`). Tests:
`tests/Feature/Platform/ThawaniGatewayTest.php`.

## 1. Flow

```
App ── POST /payments/initialize | /wallet/topups ──► payment (pending) + signed payment_url
App opens payment_url ──► /payment/redirect/{tx} ──► Thawani session (open one reused) ──► 302 Thawani page
Visitor pays ──► Thawani ──► /payment/success?transaction_id=…&t=…  (we ask Thawani, then show the result)
                        └──► webhook checkout.completed ──► stored ──► job asks Thawani ──► completed
App polls /payments/status | /wallet/topups/{id} ──► asks Thawani (at most every 8 s per payment)
Scheduler, every minute ──► payments:reconcile ──► asks Thawani for due payments
```

Thawani is the source of truth: the return pages, webhooks, app polls and the
reconciler all call `ThawaniCheckout::reconcile()`, which reads the session from
the Thawani API and applies it through `PaymentFulfillmentService` (row-locked,
idempotent: tickets, wallet credit, deposits and points happen exactly once,
whichever path arrives first). One check runs at a time per payment (cache
lock). A webhook body is never trusted on its own. A session is always checked
on the host (UAT / production) it was created on, so switching a tenant to
production does not strand open UAT payments.

**Cancelling.** When the visitor cancels (in the app, or on Thawani's cancel
page) Thawani is asked first, then its open sessions are cancelled there, so
the page cannot be paid after we gave up. If Thawani cannot cancel a session,
it is still watched: a later payment completes the order (*late capture*), and
points given back on cancel are taken again (if the balance no longer covers
them, the payment is flagged `late_capture_points`).

**One order, one charge.** Opening a new session cancels the payment's older
open one first; after a payment completes, its other open sessions are
cancelled. If an old session is paid anyway, the payment is flagged
`duplicate_capture` (refund the extra charge from the Thawani portal).

A paid session is only fulfilled when Thawani's `total_amount` and currency
equal the payment's. Otherwise the payment is flagged **needs review**
(`amount_mismatch`) and nothing is issued.

The driver is chosen per tenant (*Admin → Tenants → Payment gateway*, setting
`payments.driver`), else `PAYMENT_DRIVER` (default `thawani`). A payment keeps
the driver it was created with, so switching gateways never strands a payment.

## 2. Thawani API used

| Call | Use |
|---|---|
| `POST /api/v1/checkout/session` | Create a session: `client_reference_id` = `{transaction_id}-{n}`, one product line for the exact amount (baisa = our minor units), success / cancel URLs, metadata (transaction id, payment id, type). |
| `GET /api/v1/checkout/session/{id}` | `payment_status`: `unpaid` / `paid` / `cancelled`; `invoice`, `total_amount`, `expire_at`. |
| `POST /api/v1/checkout/{id}/cancel` | Best effort when we give up on an unpaid payment. |
| `GET /api/v1/payments?checkout_invoice=` | Thawani's `payment_id` (needed for refunds). |
| `POST /api/v1/refunds` | Full refund: `{payment_id, reason, metadata}`. |
| Pay page | `{host}/pay/{session_id}?key={publishable_key}` |

Hosts: `uatcheckout.thawani.om` (UAT) and `checkout.thawani.om` (production).
Header `thawani-api-key: <secret key>`. Checked against UAT (28 Oct 2026):
create, get and cancel session (cancel is `checkout/{id}/cancel`;
`checkout/session/{id}/cancel` answers 404), an unknown session answers 400
`session not found` (so *Test connection* treats 400 as "keys accepted"),
sessions expire 24 hours after creation, and the card page asks for a 3-D
Secure OTP (UAT test card 4242 4242 4242 4242, any future expiry, OTP 1234).
Paid sessions (top-up and ticket) completed through the return page, `GET
payments` returned the payment (`status: successful`, masked card), and `POST
refunds` refunded a ticket (`status: successful`; a same-day refund is a void,
"Void added successfully"). Still to confirm with a webhook: the unit of
`thawani-timestamp` (seconds or milliseconds; both are accepted) and the
refund event names (refund webhooks are recorded but not needed).

## 3. Retries

**HTTP** (`GatewayHttp`, `config/payments.php` → `http`):

- Connect timeout 5 s, request timeout 15 s (8 s while a visitor waits).
- Retried with exponential backoff and jitter on network errors, 408, 425, 429
  and 5xx; `Retry-After` is honoured. 2 attempts while a visitor waits
  (`interactive`), 4 in jobs (`background`).
- **Create session and refund are not idempotent.** They are retried only when
  the request provably never reached Thawani: DNS / connect failure, 425, 429
  (and 503 for create session; a refund is money out, so a 503 there counts as
  unclear). After a timeout or another 5xx the outcome is unknown, so they are
  not resent: a session simply is not created (the visitor's *Try again*
  creates a new one; an orphan unpaid session expires), and a refund goes to
  **needs review** (a person checks the Thawani portal, then confirms the
  retry). A refund job stopped while its request was out (timeout, crash) also
  goes to needs review.
- Each attempt is a row in `payment_gateway_calls`; attempts of one operation
  share a `correlation_id`.

**Circuit breaker** (`CircuitBreaker`, per gateway + mode, in the cache):
after 8 consecutive failures, calls fail fast for 30 s instead of tying up
PHP workers on 15 s timeouts; then one trial call decides. Visitors see "the
gateway can't be reached, try again" with a retry button.

**Jobs** (queue `payments`, worked first):

| Job | Tries | Backoff | Unique per |
|---|---|---|---|
| `ProcessPaymentWebhook` | 8 | 5 s … 10 min | webhook event |
| `ReconcilePayment` | 3 | 10 s, 30 s | payment |
| `ProcessGatewayRefund` | 6 | 30 s … 30 min | refund |

Job timeouts are 150 s (above the HTTP budget); the queue's `retry_after` is
300 s (`REDIS_QUEUE_RETRY_AFTER`), so a slow job is never handed to a second
worker. Deadlocks in the fulfilment transaction are retried (3 attempts).

## 4. Records

| Table | One row per | Kept |
|---|---|---|
| `payment_events` | State change or gateway step of a payment, with its source (`api`, `redirect`, `webhook`, `reconciler`, `poll`, `admin`, `scheduler`, `system`) and actor | always |
| `payment_gateway_sessions` | Thawani session (mode, status, invoice, expiry, last answer) | always |
| `payment_gateway_calls` | HTTP attempt: operation, URL, request (secrets redacted), response (truncated 64 KB), outcome, status, duration | `PAYMENT_LOG_RETENTION_DAYS` (400) |
| `payment_webhook_events` | Webhook delivery (identical bodies counted in `deliveries`): raw body, headers, signature result, status, attempts, error | 400 days (processed / ignored / rejected) |
| `payment_refunds` | Refund to the card: status, attempts, Thawani ids, last error | always |

The secret key travels only in the request header and is never stored. On
`payments`: `gateway_session_id`, `gateway_payment_id`, `next_check_at`,
`last_checked_at`, `check_count`, `needs_review`, `review_reason`.

Admin: each payment page shows the timeline, sessions, calls and webhooks
(with *Check with the gateway now* and *Replay*); *Admin → Payments → Gateway
log* lists all calls and webhooks with 24-hour failure counts; the payments list
filters **Needs review**.

## 5. Webhooks

`POST /api/v1/payments/webhooks/thawani/{tenant-slug}` (rate limit 1200/min per
IP). The delivery is stored first, then queued; the response is immediate.

- Signature: `thawani-signature` = hex HMAC-SHA256 of `raw_body + "-" +
  thawani-timestamp` with the webhook secret **of the tenant that owns the
  payment** (the URL's tenant must match). Rejected (401) and kept as
  `rejected` with the reason and the first 2 KB of the body: wrong signature,
  timestamp older than 10 minutes, tenant mismatch, unknown tenant, or no
  webhook secret configured (payments still complete through polls and the
  reconciler). Bodies over 64 KB are refused (413). Rejected rows are pruned
  after 30 days.
- Keys are one set: a tenant with its own Thawani secret key uses its own
  publishable key and webhook secret too, never the platform's.
- A delivery that cannot be applied yet (Thawani unreachable) stays `received`
  and is retried by the job; one left `received` for 5 minutes (lost job) is
  queued again by `payments:reconcile`.

## 6. Reconciliation (`payments:reconcile`, every minute)

1. Pending Thawani payments are checked at 1, 2, 3, 5, 10, 15, 30, 60 minutes
   and then hourly (`reconcile.schedule_minutes`). The next check is scheduled
   before calling Thawani, so a payment that keeps failing does not block the
   others; app polls and return pages check out of turn without moving the
   schedule. Payments flagged for review (e.g. amount mismatch) are skipped.
2. After `PAYMENT_GIVE_UP_AFTER_MINUTES` (120) an unpaid payment gets a final
   check, its open sessions are cancelled at Thawani, and it is cancelled here
   (reserved points come back, the top-up is cancelled, a box office order frees
   its seats on its own schedule).
3. A payment whose page was never opened (no session) is cancelled after the
   same time, without a call.
4. **Late captures and double charges:** a session still open at Thawani after
   its payment was cancelled, or paid through another session, is checked
   every 30 minutes until it expires and once after; a payment is completed
   (`late_capture`) or flagged (`duplicate_capture`). A session Thawani no
   longer recognises (4xx) is recorded; a production one flags the payment
   `session_unverifiable` when it is given up.
5. Lost jobs are queued again: webhooks still `received` after 5 minutes,
   refunds still `pending` after 2 hours. The command's overlap lock expires
   after 5 minutes, so a scheduler killed mid-run does not stop it.

Bank Muscat and fake payments keep `payments:expire-pending` (hourly).

## 7. Refunds

*Admin → Payments → (payment) → Refund* (completed ticket payments): tickets
are cancelled, deposits released and points restored at once, and a
`payment_refunds` row is queued to `POST /refunds`. Statuses: `pending` →
`processing` → `succeeded`; `failed` after the last try or a rejection;
`needs_review` when the answer is unknown. Failed or needs-review refunds flag
the payment and can be resent from the payment page (needs review requires
confirming the Thawani portal shows no refund). Thawani refunds the full
amount only.

Wallet top-ups are refunded through wallet refund requests, as before.

## 8. Configuration

| Where | What |
|---|---|
| *Admin → Tenants → Payment gateway* (platform admins) | Driver, Thawani environment (UAT / Production), secret key, publishable key, webhook secret (encrypted, write-only), the webhook URL to copy, **Test connection**. |
| `.env` / Coolify | `PAYMENT_DRIVER`, `THAWANI_MODE`, `THAWANI_SECRET_KEY`, `THAWANI_PUBLISHABLE_KEY`, `THAWANI_WEBHOOK_SECRET` (platform fallback), `PAYMENT_GIVE_UP_AFTER_MINUTES`, `PAYMENT_LOG_RETENTION_DAYS`, `PAYMENT_QUEUE`, `PAYMENT_HTTP_TIMEOUT`, `PAYMENT_HTTP_CONNECT_TIMEOUT`, `PAYMENT_CIRCUIT_THRESHOLD`, `PAYMENT_CIRCUIT_COOLDOWN`. |
| `config/payments.php` | Retry profiles, reconciliation schedule, batch size, poll throttle. |

Run a queue worker on `payments` (the compose `worker` does:
`--queue=payments,default`) and the scheduler. With several app servers the
cache must be shared (Redis) for the circuit breaker, locks and unique jobs.

Go-live: [DEPLOY_COOLIFY.md §9](DEPLOY_COOLIFY.md#9-thawani-pay).
