<?php

namespace App\Services\Gate;

use App\Models\BoxOfficeOrder;
use App\Models\BoxOfficeReceipt;
use App\Models\Payment;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentRefund;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\Payments\GatewayCheckouts;
use Illuminate\Support\Collection;

/**
 * Morning reconciliation of one gate business day (docs/gate_reconciliation.md):
 * box office orders created in the GateDay window, their amounts per gate,
 * per staff member and in total split by channel (link through Thawani,
 * link through Amwal, card terminal), figures to compare with each gateway's
 * merchant portal and each terminal's settlement slip, and the exceptions a
 * person must clear.
 *
 * Read-only: the actions (check with the gateway, refunds, receipt
 * decisions) are the existing ones the rows link to.
 */
class GateReconciliation
{
    public const CHANNELS = ['link_thawani', 'link_amwal', 'pos', 'other'];

    /** Exception kinds, in the order the morning list shows them (docs/gate_reconciliation.md §2). */
    public const KINDS = ['paid_no_tickets', 'payment_review', 'open_attempt', 'order_pending', 'pos_unverified', 'pos_held', 'pos_rejected', 'pos_no_receipt'];

    /** Order-level kinds a supervisor clears by marking the order checked (nothing to change in the system). */
    public const CHECKABLE_KINDS = ['order_pending', 'pos_held', 'pos_rejected', 'pos_no_receipt'];

    /**
     * @return array{date: string, from: \Carbon\Carbon, to: \Carbon\Carbon, orders: Collection, payments: Collection,
     *               totals: array, per_gate: list<array>, per_staff: list<array>, portal: array, terminals: list<array>,
     *               exceptions: list<array>, channels_used: list<string>}
     */
    public function report(Tenant $tenant, string $date): array
    {
        [$from, $to] = GateDay::window($date, $tenant);
        $orders = BoxOfficeOrder::withoutTenantScope()->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->with(['gate', 'staff:id,name', 'receipts'])
            ->orderBy('id')->get();

        // Every payment made for those orders (failed attempts, the other gateway, late and double captures).
        $payments = collect();
        foreach ($orders->pluck('id')->chunk(500) as $ids) {
            $payments = $payments->concat(Payment::withoutTenantScope()->where('tenant_id', $tenant->id)
                ->where('created_at', '>=', $from)->where('created_at', '<', $to->copy()->addDays(2))
                ->whereIn('metadata->box_office_order_id', $ids->map(fn ($id) => (int) $id)->values()->all())
                ->get());
        }
        $payments = $payments->keyBy('id');
        $paymentIds = $payments->keys()->all();

        $ticketCounts = $paymentIds
            ? Ticket::withoutTenantScope()->whereIn('transaction_id', $payments->pluck('transaction_id')->all())
                ->selectRaw('transaction_id, count(*) as c')->groupBy('transaction_id')->pluck('c', 'transaction_id')
            : collect();
        $sessions = $paymentIds ? PaymentGatewaySession::withoutGlobalScopes()->whereIn('payment_id', $paymentIds)->get() : collect();
        $refunds = $paymentIds ? PaymentRefund::withoutGlobalScopes()->whereIn('payment_id', $paymentIds)->get() : collect();

        $channel = function (BoxOfficeOrder $o) use ($payments): string {
            if ($o->isCardTerminal()) {
                return 'pos';
            }
            if ((int) $o->total_minor === 0) {
                return 'other';
            }

            return match ($payments->get($o->payment_id)?->gateway) {
                'thawani' => 'link_thawani',
                'amwal' => 'link_amwal',
                default => 'other',
            };
        };

        $group = function (Collection $rows) use ($channel): array {
            $paid = $rows->where('status', BoxOfficeOrder::STATUS_PAID);
            $amounts = array_fill_keys(self::CHANNELS, 0);
            $counts = array_fill_keys(self::CHANNELS, 0);
            foreach ($paid as $o) {
                $c = $channel($o);
                $amounts[$c] += (int) $o->total_minor;
                $counts[$c]++;
            }

            return [
                'orders' => $rows->count(),
                'paid' => $paid->count(),
                'tickets' => (int) $paid->sum('quantity'),
                'amounts' => $amounts,
                'counts' => $counts,
                'total' => array_sum($amounts),
            ];
        };

        $perGate = $orders->groupBy(fn ($o) => (string) ($o->gate_id ?? ''))
            ->map(fn ($rows) => ['label' => $rows->first()->gate ? $rows->first()->gate->code.' · '.$rows->first()->gate->localizedName() : null,
                'id' => $rows->first()->gate_id] + $group($rows))
            ->sortByDesc('total')->values()->all();
        $perStaff = $orders->groupBy(fn ($o) => (string) ($o->staff_user_id ?? ''))
            ->map(fn ($rows) => ['label' => $rows->first()->staff?->name, 'id' => $rows->first()->staff_user_id] + $group($rows))
            ->sortByDesc('total')->values()->all();
        $totals = $group($orders);

        // Merchant portal: every attempt the gateway captured (double captures included), and refunds sent through it.
        $portal = [];
        foreach (['thawani', 'amwal'] as $g) {
            $captured = $sessions->where('gateway', $g)->where('status', PaymentGatewaySession::PAID);
            $done = $refunds->where('status', PaymentRefund::SUCCEEDED)->filter(fn ($r) => $payments->get($r->payment_id)?->gateway === $g);
            $portal[$g] = [
                'captured_count' => $captured->count(),
                'captured_minor' => (int) $captured->sum('amount_minor'),
                'refunded_count' => $done->count(),
                'refunded_minor' => (int) $done->sum('amount_minor'),
            ];
        }

        // Card terminals: accepted receipts by the terminal ID read from the slip.
        $terminals = $orders->filter->isCardTerminal()
            ->flatMap(fn (BoxOfficeOrder $o) => $o->receipts->where('accepted', true)->map(fn (BoxOfficeReceipt $r) => ['order' => $o, 'receipt' => $r]))
            ->groupBy(fn ($x) => (string) ($x['receipt']->terminal_id ?? ''))
            ->map(fn ($rows, $tid) => [
                'terminal_id' => (string) $tid !== '' ? (string) $tid : null, // numeric keys come back as ints
                'count' => $rows->count(),
                'minor' => (int) $rows->sum(fn ($x) => $x['order']->total_minor),
                'refunded_count' => $rows->filter(fn ($x) => $x['order']->status === BoxOfficeOrder::STATUS_REFUNDED)->count(),
                'refunded_minor' => (int) $rows->filter(fn ($x) => $x['order']->status === BoxOfficeOrder::STATUS_REFUNDED)->sum(fn ($x) => $x['order']->total_minor),
            ])->sortBy(fn ($row) => $row['terminal_id'] ?? 'zzz')->values()->all();

        return [
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'orders' => $orders,
            'payments' => $payments,
            'totals' => $totals,
            'per_gate' => $perGate,
            'per_staff' => $perStaff,
            'portal' => $portal,
            'terminals' => $terminals,
            'exceptions' => $this->exceptions($orders, $payments, $ticketCounts, $sessions),
            'channels_used' => array_values(array_filter(self::CHANNELS, fn ($c) => $c !== 'other' || $totals['counts']['other'] > 0)),
        ];
    }

    /** @return list<array{kind: string, order: ?BoxOfficeOrder, payment: ?Payment, amount_minor: int, reason: ?string}> */
    private function exceptions(Collection $orders, Collection $payments, Collection $ticketCounts, Collection $sessions): array
    {
        $byOrder = $orders->keyBy('id');
        $orderOf = fn (Payment $p) => $byOrder->get((int) ($p->metadata['box_office_order_id'] ?? 0));
        $rows = [];
        $add = function (string $kind, ?BoxOfficeOrder $order, ?Payment $payment, ?string $reason = null) use (&$rows) {
            $rows[] = ['kind' => $kind, 'order' => $order, 'payment' => $payment,
                'amount_minor' => (int) ($payment?->amount_minor ?? $order?->total_minor ?? 0), 'reason' => $reason];
        };

        $covered = []; // orders already on the list through one of their payments
        foreach ($payments as $p) {
            /** @var Payment $p */
            $order = $orderOf($p);
            // Money taken, nothing issued (late capture without seats, a second capture of a paid order).
            if ($p->status === 'completed' && (int) $p->amount_minor > 0 && (int) ($ticketCounts[$p->transaction_id] ?? 0) === 0) {
                $add('paid_no_tickets', $order, $p, $p->method === Payment::METHOD_POS ? 'pos' : $p->gateway);

                continue;
            }
            // Amount mismatch, late capture, double capture, refund trouble… (refund-required ones are the row above until refunded).
            if ($p->needs_review && $p->method === Payment::METHOD_GATEWAY && $p->review_reason !== 'box_office_refund_required') {
                $add('payment_review', $order, $p, $p->review_reason);
                $covered[$order?->id] = true;
            }
            // A gateway attempt still open with no answer yet (a flagged one is the row above).
            if (in_array($p->status, ['pending', 'processing'], true) && $p->method === Payment::METHOD_GATEWAY && $p->gateway_session_id && ! $p->needs_review) {
                $add('open_attempt', $order, $p, $p->gateway);
                $covered[$order?->id] = true;
            }
        }

        foreach ($orders as $o) {
            /** @var BoxOfficeOrder $o */
            $checked = ! empty($o->meta['reconciliation_checked']);
            $unpaid = in_array($o->status, [BoxOfficeOrder::STATUS_PENDING, BoxOfficeOrder::STATUS_EXPIRED, BoxOfficeOrder::STATUS_CANCELLED], true);
            if ($o->isPending() && ! isset($covered[$o->id]) && ! $checked) {
                $add('order_pending', $o, null);
            }
            if (! $o->isCardTerminal() || (int) $o->total_minor === 0) {
                continue;
            }
            $latest = $o->receipts->sortByDesc('id')->first();
            if ($o->needs_review) {
                $add('pos_unverified', $o, $payments->get($o->payment_id));
            } elseif ($unpaid && ! $checked) {
                match (true) {
                    $latest === null => $add('pos_no_receipt', $o, null),
                    $latest->verdict === BoxOfficeReceipt::REJECTED => $add('pos_rejected', $o, null, collect($latest->reasons ?? [])->pluck('code')->implode(', ')),
                    $latest->verdict === BoxOfficeReceipt::REVIEW && ! $latest->accepted => $add('pos_held', $o, null),
                    default => null,
                };
            }
        }

        $order = array_flip(self::KINDS);
        usort($rows, fn ($a, $b) => [$order[$a['kind']], $a['order']?->id ?? 0] <=> [$order[$b['kind']], $b['order']?->id ?? 0]);

        return $rows;
    }

    /**
     * Payments of the day the "check with the gateway now" action queues:
     * Thawani / Amwal attempts still pending, and attempts still open after
     * their payment was cancelled or paid another way (late / double capture watch).
     *
     * @return list<int> payment ids
     */
    public function checkable(array $report): array
    {
        $open = PaymentGatewaySession::withoutGlobalScopes()->whereIn('payment_id', $report['payments']->keys()->all())
            ->where('status', PaymentGatewaySession::OPEN)->distinct()->pluck('payment_id')->map(fn ($id) => (int) $id)->all();

        return $report['payments']
            // Not those a person must look at (amount mismatch): the reconciler leaves them alone too.
            ->filter(fn (Payment $p) => $p->method === Payment::METHOD_GATEWAY && in_array($p->gateway, GatewayCheckouts::RECONCILED, true)
                && ! ($p->needs_review && $p->isPending())
                && (($p->gateway_session_id && in_array($p->status, ['pending', 'processing'], true)) || in_array($p->id, $open, true)))
            ->keys()->map(fn ($id) => (int) $id)->values()->all();
    }

    /** Channel of an order for the CSV (null while unpaid). */
    public function channelOf(BoxOfficeOrder $o, Collection $payments): string
    {
        if ($o->isCardTerminal()) {
            return 'pos';
        }

        return match ($payments->get($o->payment_id)?->gateway) {
            'thawani' => 'link_thawani',
            'amwal' => 'link_amwal',
            default => (int) $o->total_minor === 0 ? 'free' : 'link',
        };
    }
}
