<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Payments\BankMuscatCallbackHandler;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentFulfillmentService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Ticket checkout + payment status (contract §8).
 *
 * Security: the client never sends a price — amounts are computed by
 * CheckoutService. Every read/cancel is scoped to the authenticated owner;
 * only a verified (decrypted) gateway response can complete or fail a
 * payment.
 */
class PaymentController extends Controller
{
    public function __construct(private TenantContext $context, private CheckoutService $checkout) {}

    private function checkoutRules(): array
    {
        return [
            'event_id' => 'required_without:payable_id|integer',
            'payable_id' => 'required_without:event_id|integer', // legacy alias of event_id
            'booking_date' => 'required|date_format:Y-m-d',
            // Legacy single type (key; empty / `standard` = the event's default type)…
            'quantity' => 'required_without:items|nullable|integer|min:1|max:'.config('payments.max_tickets_per_order', 10),
            'ticket_type' => 'nullable|string|max:40',
            // …or several types (docs/ticket_types_contract.md §2).
            'items' => 'nullable|array|min:1|max:20',
            'items.*.ticket_type_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:0|max:100',
            'points_to_redeem' => 'nullable|integer|min:0',
        ];
    }

    /** items[] when sent, else the legacy single quantity + ticket_type. */
    private function items(array $data): array
    {
        return ! empty($data['items'])
            ? array_values($data['items'])
            : [['ticket_type' => $data['ticket_type'] ?? null, 'quantity' => (int) ($data['quantity'] ?? 0)]];
    }

    public static function linesPayload(array $lines, \App\Models\Tenant $tenant): array
    {
        return array_values(array_map(fn ($l) => [
            'ticket_type_id' => $l['ticket_type_id'],
            'key' => $l['key'],
            'name_text' => \App\Models\EventTicketType::pickLocale($l['name'] ?? []) ?: ($l['name_text'] ?? $l['key']),
            'quantity' => (int) $l['quantity'],
            'unit_price_minor' => (int) $l['unit_price_minor'],
            'unit_price' => $tenant->formatMoney((int) $l['unit_price_minor']),
            'line_total_minor' => (int) $l['line_total_minor'],
            'line_total' => $tenant->formatMoney((int) $l['line_total_minor']),
            'admits' => max(1, (int) ($l['admits'] ?? 1)),
        ], $lines));
    }

    /** GET /payments/quote?event_id=&booking_date=&quantity=&ticket_type=&points_to_redeem= */
    public function quote(Request $request)
    {
        $data = $request->validate($this->checkoutRules());
        $tenant = $this->context->get();

        $q = $this->checkout->quoteItems($tenant, (int) ($data['event_id'] ?? $data['payable_id']), $data['booking_date'],
            $this->items($data), (int) ($data['points_to_redeem'] ?? 0), $request->user('sanctum'));

        $fmt = fn (int $m) => $tenant->formatMoney($m);

        return response()->json([
            'success' => true,
            'data' => [
                'event_id' => $q['event']->id,
                'quantity' => $q['quantity'],
                'ticket_type' => $q['ticket_type'],
                'unit_price_minor' => $q['unit_price_minor'],
                'unit_price' => $q['unit_price_minor'] === null ? null : $fmt($q['unit_price_minor']),
                'lines' => self::linesPayload($q['lines'], $tenant),
                'subtotal_minor' => $q['subtotal_minor'],
                'subtotal' => $fmt($q['subtotal_minor']),
                'points_to_redeem' => $q['points_to_redeem'],
                'points_discount_minor' => $q['points_discount_minor'],
                'points_discount' => $fmt($q['points_discount_minor']),
                'deposit_minor' => $q['deposit_minor'],
                'deposit' => $fmt($q['deposit_minor']),
                'total_minor' => $q['total_minor'],
                'total' => $fmt($q['total_minor']),
                'currency' => $tenant->currency(),
            ],
        ]);
    }

    /**
     * POST /payments/initialize
     * {event_id, booking_date, quantity, ticket_type?, method: gateway|wallet, points_to_redeem?, idempotency_key}
     * Any client-sent `amount` is ignored.
     */
    public function initialize(Request $request)
    {
        $data = $request->validate($this->checkoutRules() + [
            'method' => 'nullable|in:gateway,wallet',
            'idempotency_key' => 'required_if:method,wallet|nullable|string|max:100',
        ]);
        $data['event_id'] = (int) ($data['event_id'] ?? $data['payable_id']);
        $data['method'] = $data['method'] ?? Payment::METHOD_GATEWAY;
        $data['items'] = $this->items($data);

        $payment = $this->checkout->initialize($request->user(), $this->context->get(), $data);

        return response()->json($this->checkoutResponse($payment), $payment->wasRecentlyCreated ? 201 : 200);
    }

    private function checkoutResponse(Payment $payment): array
    {
        $tenant = $this->context->get();
        $base = [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'status' => $payment->status,
            'method' => $payment->method,
            'amount_minor' => $payment->amount_minor,
            'amount' => $tenant->formatMoney((int) $payment->amount_minor),
            'currency' => $payment->currency,
            'lines' => self::linesPayload((array) ($payment->metadata['lines'] ?? []), $tenant),
        ];

        if ($payment->status === 'completed') {
            $tickets = Ticket::with('event')->where('transaction_id', $payment->transaction_id)->get();
            $data = $base + ['tickets' => TicketResource::collection($tickets)->resolve()];

            return ['success' => true, 'message' => 'Payment completed'] + $base + ['data' => $data];
        }

        $url = $this->checkout->paymentUrl($payment);
        $data = $base + ['payment_url' => $url];

        // Legacy top-level keys (checkout_url / redirect_required) for older app builds.
        return ['success' => true, 'message' => 'Payment initialized'] + $base + [
            'payment_url' => $url,
            'checkout_url' => $url,
            'redirect_required' => $url !== null,
            'data' => $data,
        ];
    }

    private function ownedPayment(Request $request, string $transactionId): Payment
    {
        return Payment::where('user_id', $request->user()->id)
            ->where('transaction_id', $transactionId)
            ->firstOrFail();
    }

    /** A pending Thawani payment is checked with Thawani (throttled) before answering a poll. */
    private function fresh(Payment $payment): Payment
    {
        return app(\App\Services\Payments\ThawaniCheckout::class)->checkIfStale($payment);
    }

    private function statusPayload(Payment $payment): array
    {
        $tenant = $this->context->get();

        return [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'payment_type' => $payment->payment_type,
            'method' => $payment->method,
            'status' => $payment->status,
            'payment_status' => $payment->status,
            'amount_minor' => $payment->amount_minor,
            'amount' => $tenant->formatMoney((int) $payment->amount_minor),
            'currency' => $payment->currency,
            'paid_at' => \App\Support\TenantTime::iso($payment->paid_at),
        ];
    }

    /** POST /payments/confirm {transaction_id} — owner only, read-only. */
    public function confirm(Request $request)
    {
        $request->validate(['transaction_id' => 'required|string']);
        $payment = $this->fresh($this->ownedPayment($request, $request->input('transaction_id')));
        $completed = $payment->status === 'completed';

        return response()->json([
            'success' => $completed,
            'message' => $completed ? 'Payment successful' : 'Payment not completed',
            'data' => $this->statusPayload($payment),
        ] + $this->statusPayload($payment));
    }

    /** GET /payments/status/{transactionId} — owner only. */
    public function checkStatus(Request $request, string $transactionId)
    {
        $payment = $this->fresh($this->ownedPayment($request, $transactionId));

        return response()->json(['success' => true, 'data' => $this->statusPayload($payment)] + $this->statusPayload($payment));
    }

    /**
     * POST /payments/cancel {transaction_id} — the owner abandons a pending
     * checkout (e.g. closed the payment page). Gateway-side cancellations
     * arrive encrypted via /payment/cancel instead.
     */
    public function cancel(Request $request, PaymentFulfillmentService $fulfillment)
    {
        $request->validate(['transaction_id' => 'required_without:order_id|string', 'order_id' => 'nullable|string']);
        $payment = $this->ownedPayment($request, $request->input('transaction_id') ?? $request->input('order_id'));

        if ($payment->status === 'pending') {
            // Thawani: asked first and its sessions cancelled, so the open
            // payment page cannot be paid after the app gave up on it.
            $payment = $payment->gateway === \App\Services\Payments\Gateway\ThawaniGateway::NAME
                ? app(\App\Services\Payments\ThawaniCheckout::class)->cancel($payment, 'api', 'cancelled_in_app')
                : $fulfillment->fail($payment, 'cancelled', [], 'api', ['reason' => 'cancelled_in_app']);
        }

        return response()->json([
            'success' => true,
            'message' => $payment->status === 'cancelled' ? 'Payment cancelled successfully' : 'Payment can no longer be cancelled',
            'data' => $this->statusPayload($payment),
        ]);
    }

    /**
     * POST /payments/webhook — Bank Muscat server-to-server callback. The
     * encrypted payload (tenant working key) is the integrity check.
     */
    public function webhook(Request $request, BankMuscatCallbackHandler $handler)
    {
        $allowedIps = config('services.bank_muscat.webhook_ips', []);
        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps, true)) {
            Log::warning('Rejected payment webhook from unlisted IP', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $encResponse = (string) $request->input('encResp', '');
        if ($encResponse === '') {
            return response()->json(['success' => false, 'message' => 'No response data'], 400);
        }

        $result = $handler->handle($encResponse, $request->query('transaction_id'));
        if (! $result) {
            return response()->json(['success' => false, 'message' => 'Invalid payment response'], 400);
        }

        return response()->json(['success' => true]);
    }

    /** GET /payments/{id} — owner only. */
    public function show(Request $request, int $id)
    {
        $payment = Payment::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json(['success' => true, 'data' => array_merge($this->statusPayload($payment), [
            'metadata' => collect($payment->metadata ?? [])->except(['user_phone'])->all(),
            'created_at' => \App\Support\TenantTime::iso($payment->created_at),
        ])]);
    }

    /** GET /payments/history */
    public function myTransactions(Request $request)
    {
        $page = Payment::where('user_id', $request->user()->id)->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn (Payment $p) => array_merge($this->statusPayload($p), [
                'created_at' => \App\Support\TenantTime::iso($p->created_at),
                'metadata' => collect($p->metadata ?? [])->only(['event_id', 'quantity', 'ticket_type', 'booking_date'])->all(),
            ]))->values(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
