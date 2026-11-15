<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcilePayment;
use App\Models\AuditLog;
use App\Models\BoxOfficeOrder;
use App\Models\Payment;
use App\Services\Gate\BoxOfficeService;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateReconciliation;
use App\Services\Payments\PaymentRecorder;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Admin → Gates → Morning reconciliation (docs/gate_reconciliation.md):
 * one gate business day (default: yesterday), totals per gate / staff /
 * channel, gateway portal and card terminal figures, the exceptions list,
 * a CSV export and "check with the gateway now". Permission `manage gates`
 * (gate supervisors, like Gate sales) or `view payments` (finance, like
 * Payments); order links show for the first, payment links for the second.
 */
class GateReconciliationController extends Controller
{
    public function __construct(private TenantContext $context, private GateReconciliation $reconciliation, private BoxOfficeService $boxOffice) {}

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->canAny(['manage gates', 'view payments']), 403);
    }

    private function date(Request $request): string
    {
        $tenant = $this->context->get();

        return $request->filled('date')
            ? GateDay::parse((string) $request->input('date'), $tenant)
            : Carbon::parse(GateDay::today($tenant))->subDay()->toDateString();
    }

    /** Orders the scheduler has not expired yet (as on Gate sales). */
    private function expireDue(): void
    {
        BoxOfficeOrder::where('status', BoxOfficeOrder::STATUS_PENDING)->where('expires_at', '<=', now())->limit(200)->get()
            ->each(fn (BoxOfficeOrder $o) => $this->boxOffice->expireIfDue($o));
    }

    public function index(Request $request)
    {
        $this->authorizeAccess($request);
        $tenant = $this->context->get();
        $date = $this->date($request);
        $this->expireDue();
        $report = $this->reconciliation->report($tenant, $date);

        return view('admin.gates.reconciliation', [
            'report' => $report,
            'date' => $date,
            'today' => GateDay::today($tenant),
            'checkable' => count($this->reconciliation->checkable($report)),
            'rollover' => GateDay::rolloverHour($tenant),
            'canPayments' => (bool) $request->user()?->can('view payments'),
            'canGates' => (bool) $request->user()?->can('manage gates'),
        ]);
    }

    /** One row per order of the day, plus one per extra payment captured for an order (late / double capture). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $tenant = $this->context->get();
        $date = $this->date($request);
        $report = $this->reconciliation->report($tenant, $date);
        $tz = TenantTime::zone($tenant);
        $kinds = collect($report['exceptions'])->groupBy(fn ($e) => $e['order']?->id ?? 0)
            ->map(fn ($rows) => $rows->pluck('kind')->unique()->implode(' '));
        $byPayment = collect($report['exceptions'])->filter(fn ($e) => $e['payment'])->groupBy(fn ($e) => $e['payment']->id)
            ->map(fn ($rows) => $rows->pluck('kind')->unique()->implode(' '));
        $money = fn (?int $minor) => $minor === null ? '' : $tenant->formatMoney($minor);
        $time = fn ($t) => $t ? $t->copy()->setTimezone($tz)->format('Y-m-d H:i:s') : '';

        AuditLog::record('box_office.reconciliation_exported', $tenant, ['date' => $date]);

        $headers = ['reference', 'created_at', 'gate', 'staff', 'order_status', 'channel', 'gateway', 'transaction_id', 'merchant_reference',
            'gateway_payment_id', 'payment_status', 'amount', 'currency', 'paid_at', 'terminal_id', 'rrn', 'approval_code', 'card_last4',
            'receipt_verdict', 'needs_review', 'review_reason', 'exceptions'];

        return response()->streamDownload(function () use ($report, $headers, $money, $time, $kinds, $byPayment) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $payments = $report['payments'];
            foreach ($report['orders'] as $o) {
                /** @var BoxOfficeOrder $o */
                $p = $payments->get($o->payment_id);
                $receipt = $o->receipts->where('accepted', true)->sortByDesc('id')->first() ?? $o->receipts->sortByDesc('id')->first();
                $row = [$o->reference, $time($o->created_at), $o->gate?->code ?? '', $o->staff?->name ?? '', $o->status,
                    $this->reconciliation->channelOf($o, $payments), $p?->gateway ?? '', $p?->transaction_id ?? '', $p?->gateway_session_id ?? '',
                    $p?->gateway_payment_id ?? '', $p?->status ?? '', $money((int) $o->total_minor), $o->currency, $time($o->paid_at),
                    $receipt?->terminal_id ?? '', $receipt?->rrn ?? '', $receipt?->approval_code ?? '', $receipt?->card_last4 ?? '',
                    $receipt?->verdict ?? '', $p?->needs_review ? 'yes' : '', $p?->review_reason ?? '', $kinds->get($o->id, '')];
                fputcsv($out, array_map([SatisfactionController::class, 'cell'], $row));

                // Other captured payments of this order (the merchant portal lists them too).
                foreach ($payments->filter(fn (Payment $x) => (int) ($x->metadata['box_office_order_id'] ?? 0) === $o->id && $x->id !== $o->payment_id
                    && in_array($x->status, ['completed', 'refunded'], true)) as $x) {
                    fputcsv($out, array_map([SatisfactionController::class, 'cell'], [$o->reference, $time($x->created_at), $o->gate?->code ?? '',
                        $o->staff?->name ?? '', $o->status, 'extra_payment', $x->gateway ?? $x->method, $x->transaction_id, $x->gateway_session_id ?? '',
                        $x->gateway_payment_id ?? '', $x->status, $money((int) $x->amount_minor), $x->currency, $time($x->paid_at), '', '', '', '', '',
                        $x->needs_review ? 'yes' : '', $x->review_reason ?? '', $byPayment->get($x->id, '')]));
                }
            }
            fclose($out);
        }, 'gate-reconciliation-'.$tenant->slug.'-'.$date.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** POST: queue a gateway check (the reconciler's job) for the day's open Thawani / Amwal attempts. */
    public function check(Request $request, PaymentRecorder $recorder)
    {
        $this->authorizeAccess($request);
        $tenant = $this->context->get();
        $date = $this->date($request);
        $report = $this->reconciliation->report($tenant, $date);
        $ids = array_slice($this->reconciliation->checkable($report), 0, 500);
        foreach ($ids as $id) {
            $recorder->record($report['payments']->get($id), 'check_requested', 'admin', ['via' => 'gate_reconciliation']);
            ReconcilePayment::dispatch($id, false, 'admin');
        }
        AuditLog::record('box_office.reconciliation_checked', $tenant, ['date' => $date, 'payments' => count($ids)]);

        return redirect()->route('admin.gates.reconciliation', ['date' => $date])->with('success', $ids
            ? __('طُلب التحقق من بوابة الدفع لعدد من الدفعات: :count. حدّث الصفحة بعد دقيقة.', ['count' => count($ids)])
            : __('لا توجد دفعات مفتوحة لدى بوابة الدفع في هذا اليوم.'));
    }

    /**
     * POST orders/{order}/checked — a supervisor checked an order-level
     * exception (unpaid card terminal sale, held / rejected receipt, order
     * left pending) against the terminal slip or with the staff member.
     */
    public function markChecked(Request $request, BoxOfficeOrder $order)
    {
        $this->authorizeAccess($request);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $order->forceFill(['meta' => array_merge($order->meta ?? [], ['reconciliation_checked' => [
            'by' => $request->user()->id, 'at' => now()->toIso8601String(), 'note' => $data['note'] ?? null,
        ]])])->save();
        AuditLog::record('box_office.reconciliation_order_checked', $order, ['reference' => $order->reference, 'note' => $data['note'] ?? null]);

        return redirect()->route('admin.gates.reconciliation', array_filter(['date' => $data['date'] ?? null]))
            ->with('success', __('سُجّلت مراجعة الطلب :reference.', ['reference' => $order->reference]));
    }
}
