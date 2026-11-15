# Gate sales: the morning reconciliation

Every morning someone checks the previous gate business day: what the gates
sold, that the money in the payment gateways and on the card terminals
matches, and that no visitor paid without getting tickets (or paid twice).

Page: *Admin → Gates → مطابقة مبيعات البوابات* (`/admin/gates/reconciliation`).
Open to users with **`manage gates`** (gate supervisors) or **`view payments`**
(finance). The day defaults to **yesterday**; a gate business day runs from
04:00 to 04:00 (tenant setting `gate.day_rollover_hour`, organization time) and
the page counts the box office orders **created** in that window.
Code: `app/Services/Gate/GateReconciliation.php`,
`app/Http/Controllers/Admin/GateReconciliationController.php`. Tests:
`tests/Feature/Gate/GateReconciliationTest.php`, `BoxOfficeGatewayTest.php`.

## 1. Procedure (about 10 minutes)

| # | Who | What |
|---|---|---|
| 1 | Finance | Open the page (yesterday). Press **تحقّق من بوابة الدفع الآن**: every Thawani / Amwal attempt of the day still pending, and every Amwal attempt still watched for a late payment, is asked again through the reconciler's job. Wait a minute and reload. |
| 2 | Finance | **Gateway portals** card: for Thawani and Amwal, compare *captured attempts / amount* with the approved transactions of the day in each merchant portal, and *refunded* with the portal's refunds. The figures include double and late captures, which the portal shows too. |
| 3 | Gate supervisor | **Card terminals** card: compare each terminal ID's count and amount with that terminal's settlement slip of the night. *لم يُقرأ* = receipts whose terminal ID could not be read: match them by amount and time from the CSV. |
| 4 | Both | Work through **حالات تحتاج إلى معالجة** (§2) until it is empty. Each row links to the order (supervisors) and to the payment (finance). |
| 5 | Finance | **تصدير CSV** and keep it with the portal and terminal reports of the day. |

The per-gate and per-staff tables (orders, paid orders, amount per channel:
link through Thawani, link through Amwal, card terminal) answer "how much did
gate X / staff member Y take" and are the figures for the day's report.

If a total does not match: export the CSV, sort by channel, and match row by
row (`transaction_id` and `merchant_reference` = the gateway's merchant
reference `TXN…_n` for Amwal, `client_reference_id` for Thawani; `rrn` /
`approval_code` for terminals). A captured amount that is in the portal but not
in the CSV belongs to another day's order or is not a gate sale.

## 2. Exceptions and how to clear them

A row leaves the list once its cause is dealt with on its own page.

| Row | Meaning | Clear it |
|---|---|---|
| **دُفع المبلغ ولم تصدر تذاكر** (`paid_no_tickets`) | Money was taken and nothing was issued: a payment captured after its order expired or was cancelled when the seats were gone (order `paid_unfulfilled`), a second capture of an order already paid (the visitor paid twice, e.g. on both gateways), or a card terminal sale accepted after the seats were gone. | *Gate sales → order → Refund* (or *Payments → payment → Refund*). Gateway payments are refunded to the card through Thawani / Amwal; a card terminal sale is marked refunded and the money is returned **on the terminal**. |
| **دفعة تحتاج إلى مراجعة** (`payment_review`) | A gateway payment flagged `needs_review`: `amount_mismatch` (the gateway took another amount, nothing issued), `late_capture` (paid after the order expired or was cancelled; tickets were issued because seats were free, but the visitor may have bought again another way), `duplicate_capture` (two attempts of one payment were paid), `refund_failed` / `refund_needs_review`, `session_unverifiable`. | Open the payment, check it in the merchant portal. Then *Mark as reviewed* (nothing wrong), or *Refund* (e.g. the visitor also paid at the gate). For `duplicate_capture` refund the extra charge in the portal. |
| **محاولة دفع مفتوحة لدى بوابة الدفع** (`open_attempt`) | A Thawani / Amwal attempt with no answer yet. | **تحقّق من بوابة الدفع الآن**. The reconciler also checks it on its own schedule and cancels an unpaid one two hours after creation. |
| **طلب ما زال بانتظار الدفع** (`order_pending`) | An order still within its link time (normally only on the current day). | Nothing; it expires with its link. *سجّل المراجعة* hides it. |
| **بيع بجهاز البطاقة بإيصال لم يُفحص كاملًا** (`pos_unverified`) | A card terminal sale paid with a receipt that could not be fully checked (verdict *review*, accepted because `pos_accept_unverified` is on). | Order page: match the photo with the terminal slip, then *mark the sale as checked*, or *void* it. |
| **إيصال ينتظر قرار المشرف** (`pos_held`) | A receipt held for a supervisor (`pos_accept_unverified` off); the order is not paid. | Order page: *approve the receipt* if the card was charged (tickets are issued), else *سجّل المراجعة*. |
| **رُفض الإيصال ولم يُدفع الطلب** (`pos_rejected`) | The last receipt was rejected (wrong amount / date / terminal, duplicate…) and the order was never paid. The card may still have been charged. | Check the terminal slip. Charged: approve the receipt on the order page (if the order is no longer payable, return the money on the terminal). Not charged: *سجّل المراجعة*. |
| **بيع بجهاز البطاقة بلا صورة إيصال** (`pos_no_receipt`) | A card terminal order that never got a receipt photo (cancelled or expired). | Ask the staff member, check the slip; if charged, return the money on the terminal. Then *سجّل المراجعة*. |

*سجّل المراجعة* (on order-level rows only) records who checked it and when in
the order (`meta.reconciliation_checked`) and in the audit log.

## 3. How late payments are caught

- When a gate order **expires or is cancelled** and its visitor already opened
  the payment page, the payment is closed through its gateway's own cancel
  (queued job `CancelGatewayPayment` on the `payments` queue): the gateway is
  asked first, so a payment made just before the close still completes; Thawani
  sessions are then cancelled at Thawani; Amwal attempts cannot be cancelled and
  stay **watched** by `payments:reconcile` (every 30 minutes until two hours
  after they were opened, then one last look). A page never opened is cancelled
  at once, without a gateway call.
- A payment confirmed after its order closed (SmartBox callback, Amwal
  notification, Thawani webhook, reconciler) is **never dropped**: tickets are
  issued when the seats are still free (`late_capture`), otherwise the order
  becomes `paid_unfulfilled` (`box_office_refund_required`). Either way the
  payment is flagged and appears here.
- An Amwal attempt paid more than about two hours after it was opened is only
  seen through Amwal's cloud notification (keep it registered) or a manual
  *Check with the gateway now* on the payment page.

## 4. Settings this depends on

- Payment gateways on for visitors: *Admin → Tenants → Payment gateway*
  (docs/payments_amwal.md §5). With Thawani and Amwal both on, the `/pay/{token}`
  page shows two buttons and the day has both link columns.
- Card terminals: *Branding & settings → Gate sales*: `pos_enabled`,
  `pos_accept_unverified`, `pos_terminal_ids` (with a list, a receipt from
  another terminal is rejected, so the per-terminal totals are complete).
