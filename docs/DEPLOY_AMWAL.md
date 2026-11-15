# Amwal Pay on the production server: test (UAT) first, then live

How to put Amwal Pay on the production server (`https://v2-mawsim.awj.om`,
tenant `north-batinah`) with Amwal's **test (UAT) credentials**, check it
there without touching visitors, and then switch to the live credentials.
Design and code: [payments_amwal.md](payments_amwal.md). Server set-up:
[DEPLOY_COOLIFY.md](DEPLOY_COOLIFY.md). Gate morning check:
[gate_reconciliation.md](gate_reconciliation.md).

Nothing in this release changes the database (no migrations), and a tenant
that is not touched in the admin panel keeps working exactly as before
(Thawani only).

## 1. Deploy the code

| What | Repository / branch | Coolify resource | Needed for |
|---|---|---|---|
| Backend | `awjinnovation/sohar_portal`, `main` | the Docker Compose resource (app, worker, scheduler) | everything |
| Booking site | same repository, `/booking-web` | `book-events.awj.om` | the second "Pay with …" button on the event QR page (an old build keeps one button on the default gateway) |
| Visitor web app, gate web app | `awjinnovation/soharfastival_mobile_v2`, `main` | the two web resources | gateway choice in the browser app (the gate web app needs no change, redeploy only to stay in step) |
| Android / iOS apps | same repository | stores, or the APKs in §6 | gateway choice and the Amwal SDK inside the app (older builds keep working and use the default gateway) |

For each Coolify resource: push to `main`, then *Deploy* (or let the GitHub
auto-deploy webhook do it). After the backend deploy, check:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://v2-mawsim.awj.om/up                                   # 200
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://v2-mawsim.awj.om/api/v1/payments/webhooks/amwal/north-batinah   # not 404 (the route exists)
curl -s https://v2-mawsim.awj.om/api/v1/tenant/config -H 'X-Tenant: north-batinah' | grep -o '"payments":{[^}]*}[^}]*}'  # the enabled gateways
```

The `worker` must be running: it works the `payments` queue (gateway
checks, cancelling a gate order's payment at the gateway, notifications),
and the `scheduler` runs `payments:reconcile` every minute.

The server must be able to reach Amwal's UAT host on **port 14443**
(`https://test.amwalpg.com:14443`); *Test connection* in step 2 proves it.

## 2. Enter the UAT credentials (visitors still on Thawani)

*Admin → Tenants → مهرجان صحار → بيانات بوابة الدفع*:

1. *بيئة أموال*: **UAT**.
2. Under *أموال · Amwal Pay · SmartBox*: Merchant ID (MID), Terminal ID
   (TID) and Secure hash key, as sent by Amwal. They are stored encrypted
   and never shown again.
3. Leave *بوابات الدفع المفعّلة للزوار* as it is (**only ثواني ticked**).
   Save.
4. Press **اختبر الاتصال** next to the Amwal fields. It must succeed.

Alternative for all tenants at once (Coolify environment variables, then
redeploy): `AMWAL_MODE=uat`, `AMWAL_MERCHANT_ID`, `AMWAL_TERMINAL_ID`,
`AMWAL_SECURE_KEY`. Tenant values win over these.

## 3. Register the notification URL with Amwal

```
https://v2-mawsim.awj.om/api/v1/payments/webhooks/amwal/north-batinah
```

Enter it as the merchant's cloud notification URL in the Amwal portal, or
send it to Amwal. Payments complete without it (return callback, app polls,
reconciler); it covers a visitor who closes the page before returning, and
it is the only thing that catches a payment made more than two hours late.

## 4. Test on production without visitors seeing Amwal

*Admin → المدفوعات → اختبار بوابة الدفع* (platform admins). The page charges
through the gateway you pick whether or not it is enabled for visitors, and
its payments issue no tickets and are left out of revenue.

1. Choose **أموال**, 0.100 OMR, **ادفع الآن**. Pay with Amwal's UAT test
   card. The page returns and shows **مكتمل**.
2. Press **استرداد** on that payment: void / refund succeeded.
3. *المدفوعات → سجل بوابة الدفع*: a notification from Amwal arrived for the
   payment (step 3 works).
4. Make one more payment, close the Amwal form without paying, cancel: the
   payment shows **ملغى**.

Confirm these three points on the first paid test (they are not stated in
Amwal's pages; the code asks Amwal's API if they differ, and flags the
payment for review if that does not settle it): the notification's `Amount`
format, that its MerchantId / TerminalId equal ours, and the `TxnType` of a
refund notification.

## 5. Show Amwal to visitors (still UAT: only for a closed test)

While the tenant is on UAT, a visitor who chooses Amwal cannot pay with a
real card. Do this step on production only for a short internal test, or
use the `demo` tenant for it.

*Admin → Tenants → (tenant)*: tick **أموال** as well, keep
*بوابة الدفع الافتراضية* on ثواني, save. From then on:

- the app (version 12 and later), the event QR booking page and the gate
  payment page show both gateways and the visitor chooses;
- older app builds send no choice and use the default gateway;
- unticking one gateway removes the choice everywhere (apps pick it up
  within five minutes, or at once on the next payment attempt).

*الدفع بأموال في التطبيق*: **رابط الدفع** opens Amwal's page inside the app;
**SDK** opens Amwal's native screen in app version 12 and later (older
builds and all web pages keep using the link; if the SDK cannot start, the
app falls back to the link by itself).

Test from the app and from an event QR link: top up the wallet and buy a
ticket through Amwal, then through Thawani.

## 6. Go live

1. Get the **production** MID, TID and secure hash key from Amwal, and ask
   them to confirm that *Get Transaction by Merchant Reference*, refund and
   void are enabled on the production profile, and that the notification
   URL of §3 is registered on the production merchant.
2. *Admin → Tenants → (tenant)*: *بيئة أموال* **Production**, enter the
   three production values, save, **اختبر الاتصال**.
3. *اختبار بوابة الدفع*: one 0.100 OMR payment with a real card, then
   **استرداد**.
4. Tick **أموال** under *بوابات الدفع المفعّلة للزوار* (and choose the
   default). Visitors now see it.

**Roll back:** untick أموال and save. Payments already made through Amwal
keep their gateway: their notifications, checks and refunds still work.

## 7. App downloads (Android)

The signed APKs are served by the backend at fixed addresses that do not
change between versions:

| App | Link |
|---|---|
| Suhar Festival (visitors) | `https://v2-mawsim.awj.om/downloads/suhar-festival.apk` |
| Mawsim Gate (gate staff) | `https://v2-mawsim.awj.om/downloads/mawsim-gate.apk` |
| Versions of both files | `https://v2-mawsim.awj.om/downloads/versions.json` |

To publish a new version: build in the app repository
(`flutter build apk --release --target-platform android-arm,android-arm64`
with the tenant's defines; README "Android release"), copy the two files
over `public/downloads/*.apk`, update `public/downloads/versions.json`,
commit, push and deploy the backend. The files must stay under 100 MB each
(GitHub's limit), which is why the x86_64 code is left out: every Android
phone and tablet runs the arm builds.

## 8. Every morning

*Admin → البوابات → مطابقة مبيعات البوابات*: yesterday's gate sales by
gateway and card terminal, and the cases to clear. Procedure:
[gate_reconciliation.md](gate_reconciliation.md).
