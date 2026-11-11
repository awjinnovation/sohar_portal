<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BoxOfficeOrder;
use App\Models\BoxOfficeReceipt;
use App\Models\Gate;
use App\Models\User;
use App\Services\Gate\BoxOfficeService;
use App\Services\Gate\CardTerminalService;
use App\Services\Gate\GateDay;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin → Gates → Gate sales (docs/gate_sales_contract.md §7): box office
 * orders with filters, totals per gate and per staff member, order detail
 * and cancelling a pending order. Permission `manage gates`.
 */
class GateSalesController extends Controller
{
    public function __construct(private TenantContext $context, private BoxOfficeService $boxOffice) {}

    public function index(Request $request)
    {
        $tenant = $this->context->get();
        $date = $request->input('date') === 'all' ? null : GateDay::parse($request->input('date'), $tenant);
        $q = trim((string) $request->input('q'));
        $status = in_array($request->input('status'), BoxOfficeOrder::STATUSES, true) ? $request->input('status') : null;

        // Lazily expire what the scheduler has not reached yet.
        BoxOfficeOrder::where('status', BoxOfficeOrder::STATUS_PENDING)->where('expires_at', '<=', now())->limit(200)->get()
            ->each(fn (BoxOfficeOrder $o) => $this->boxOffice->expireIfDue($o));

        $base = BoxOfficeOrder::query()
            ->when($date, function ($query) use ($date, $tenant) {
                [$from, $to] = GateDay::window($date, $tenant);
                $query->where('created_at', '>=', $from)->where('created_at', '<', $to);
            })
            ->when($request->filled('gate'), fn ($query) => $query->where('gate_id', (int) $request->input('gate')))
            ->when($request->filled('staff'), fn ($query) => $query->where('staff_user_id', (int) $request->input('staff')))
            ->when(in_array($request->input('method'), BoxOfficeOrder::METHODS, true), fn ($query) => $query->where('payment_method', $request->input('method')))
            ->when($request->boolean('review'), fn ($query) => $query->where('needs_review', true));

        $filtered = (clone $base)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D/', '', $q);
                $query->where(fn ($w) => $w->where('reference', 'like', '%'.strtoupper($q).'%')
                    ->when(strlen($digits) >= 4, fn ($x) => $x->orWhere('buyer_phone', 'like', '%'.$digits.'%')));
            });

        $orders = (clone $filtered)->with(['event:id,title,title_ar,tenant_id', 'gate', 'staff:id,name', 'lines'])
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $paidOnly = fn ($query) => $query->where('status', BoxOfficeOrder::STATUS_PAID);
        $summary = [
            'count' => (clone $base)->count(),
            'paid_count' => (clone $base)->tap($paidOnly)->count(),
            'paid_minor' => (int) (clone $base)->tap($paidOnly)->sum('total_minor'),
            'tickets' => (int) (clone $base)->tap($paidOnly)->sum('quantity'),
            'pending_count' => (clone $base)->where('status', BoxOfficeOrder::STATUS_PENDING)->count(),
        ];
        // Paid without tickets (late capture, seats gone), any day: refund needed.
        $refundDue = BoxOfficeOrder::where('status', BoxOfficeOrder::STATUS_PAID_UNFULFILLED)->count();
        // Card terminal sales accepted with a receipt that could not be fully checked, any day.
        $reviewDue = BoxOfficeOrder::where('needs_review', true)->count();
        $group = fn (string $column) => (clone $base)->toBase()
            ->selectRaw("$column as k, count(*) as orders, sum(case when status = 'paid' then 1 else 0 end) as paid, sum(case when status = 'paid' then total_minor else 0 end) as paid_minor, sum(case when status = 'paid' then quantity else 0 end) as tickets")
            ->groupBy($column)->orderByDesc('paid_minor')->get();

        $gates = Gate::orderBy('sort_order')->orderBy('id')->get();
        $staffIds = BoxOfficeOrder::query()->distinct()->pluck('staff_user_id')->filter();

        return view('admin.gates.sales', [
            'orders' => $orders,
            'summary' => $summary,
            'perGate' => $group('gate_id'),
            'perStaff' => $group('staff_user_id'),
            'gates' => $gates,
            'gateNames' => $gates->keyBy('id'),
            'staff' => User::whereIn('id', $staffIds)->orderBy('name')->get(['id', 'name', 'phone_number']),
            'date' => $date,
            'q' => $q,
            'settings' => BoxOfficeService::settings($tenant),
            'refundDue' => $refundDue,
            'reviewDue' => $reviewDue,
        ]);
    }

    public function show(BoxOfficeOrder $order)
    {
        $order = $this->boxOffice->expireIfDue($order);
        $order->load(['event', 'gate', 'device', 'staff', 'buyer', 'payment', 'lines', 'receipts.staff', 'receipts.decider', 'receipts.duplicateOf']);
        $tickets = $order->issuedTickets()->load('ticketType');

        return view('admin.gates.sales-show', [
            'order' => $order,
            'tickets' => $tickets,
            'paymentUrl' => $order->isCardTerminal() ? null : $this->boxOffice->paymentUrl($order),
            'smsRetryAfter' => $order->isPending() ? $this->boxOffice->smsRetryAfter($order) : null,
        ]);
    }

    /**
     * Refund the payment of an order that was paid without tickets (late
     * capture, seats gone) through the existing payment refund: the payment
     * becomes `refunded` (complete it in the gateway) and the order follows.
     */
    public function refund(Request $request, BoxOfficeOrder $order, \App\Services\Wallet\WalletAdminService $admin)
    {
        abort_unless($request->user()->can('refund payments'), 403);
        $payment = $order->payment;
        if ($order->status !== BoxOfficeOrder::STATUS_PAID_UNFULFILLED || ! $payment) {
            return back()->with('error', __('لا يحتاج هذا الطلب إلى استرداد.'));
        }
        try {
            $admin->refundPayment($payment, $request->user(), __('دُفع طلب البوابة بعد انتهاء صلاحيته ونفدت التذاكر.'));
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gates.sales.show', $order)->with('success', $payment->method === \App\Models\Payment::METHOD_POS
            ? 'سُجِّلت الدفعة مستردة. أعد المبلغ للزائر على جهاز البطاقة.'
            : 'سُجِّلت الدفعة مستردة. أكمل الاسترداد عبر بوابة الدفع.');
    }

    /** The receipt photo, from the private disk (docs/gate_sales_contract.md §9). */
    public function receiptImage(BoxOfficeOrder $order, BoxOfficeReceipt $receipt)
    {
        abort_unless((int) $receipt->order_id === (int) $order->id, 404);
        $disk = Storage::disk($receipt->disk);
        abort_unless($disk->exists($receipt->path), 404);

        return response($disk->get($receipt->path), 200, [
            'Content-Type' => $receipt->mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Accept a receipt the checks did not accept: the order is paid and its tickets issued. */
    public function approveReceipt(Request $request, BoxOfficeOrder $order, BoxOfficeReceipt $receipt, CardTerminalService $terminal)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        try {
            $order = $terminal->approve($order, $receipt, $request->user(), $data['note'] ?? null);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLog::record('box_office.receipt_approved', $order, ['reference' => $order->reference, 'receipt_id' => $receipt->id, 'verdict' => $receipt->verdict]);

        return redirect()->route('admin.gates.sales.show', $order)->with('success', __('اعتُمد الإيصال وصدرت التذاكر.'));
    }

    /** The sale was paid with a receipt that needed review: a supervisor keeps it. */
    public function clearReview(Request $request, BoxOfficeOrder $order, CardTerminalService $terminal)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        try {
            $order = $terminal->clearReview($order, $request->user(), $data['note'] ?? null);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLog::record('box_office.receipt_cleared', $order, ['reference' => $order->reference]);

        return redirect()->route('admin.gates.sales.show', $order)->with('success', __('سُجّلت مراجعة الإيصال.'));
    }

    /** Cancel a card terminal sale: tickets cancelled, payment and order refunded. */
    public function voidSale(Request $request, BoxOfficeOrder $order, CardTerminalService $terminal, \App\Services\Wallet\WalletAdminService $admins)
    {
        abort_unless($request->user()->can('refund payments'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try {
            $order = $terminal->void($order, $request->user(), $data['reason'], $admins);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLog::record('box_office.card_sale_voided', $order, ['reference' => $order->reference, 'reason' => $data['reason']]);

        return redirect()->route('admin.gates.sales.show', $order)->with('success', __('أُلغيت العملية وأُلغيت تذاكرها. أعد المبلغ على جهاز البطاقة إن كان قد خُصم.'));
    }

    public function cancel(Request $request, BoxOfficeOrder $order)
    {
        try {
            $this->boxOffice->cancel($order, $request->user(), 'admin');
        } catch (ApiException $e) {
            return back()->with('error', __('لا يمكن إلغاء هذا الطلب لأنه لم يعد بانتظار الدفع.'));
        }
        AuditLog::record('box_office.cancelled', $order, ['reference' => $order->reference]);

        return redirect()->route('admin.gates.sales.show', $order)->with('success', 'أُلغي الطلب');
    }
}
