<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessGatewayRefund;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentGatewayCall;
use App\Models\PaymentGatewaySession;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\Payments\Gateway\GatewayException;
use App\Services\Payments\Gateway\ThawaniGateway;
use App\Services\Payments\PaymentRecorder;
use App\Services\Payments\ThawaniCheckout;
use App\Services\Wallet\WalletAdminService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public const STATUSES = ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'];

    public function index(Request $request)
    {
        $filters = [
            'status' => in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : null,
            'type' => in_array($request->input('type'), [Payment::TYPE_TICKET, Payment::TYPE_TOPUP], true) ? $request->input('type') : null,
            'method' => in_array($request->input('method'), [Payment::METHOD_GATEWAY, Payment::METHOD_WALLET], true) ? $request->input('method') : null,
            'review' => $request->boolean('review'),
            'q' => trim((string) $request->input('q')),
        ];

        $payments = Payment::with('user')
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('payment_type', $v))
            ->when($filters['method'], fn ($q, $v) => $q->where('method', $v))
            ->when($filters['review'], fn ($q) => $q->where('needs_review', true))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $term = $filters['q'];
                $q->where(function ($w) use ($term) {
                    $w->where('transaction_id', 'like', "%$term%")
                        ->orWhereHas('user', fn ($u) => $u->where('phone_number', 'like', "%$term%"));
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'completed_sum' => (int) Payment::where('status', 'completed')->sum('amount_minor'),
            'completed_count' => Payment::where('status', 'completed')->count(),
            'refunded_sum' => (int) Payment::where('status', 'refunded')->sum('amount_minor'),
            'needs_review' => Payment::where('needs_review', true)->count(),
        ];

        return view('admin.payments.index', compact('payments', 'filters', 'summary'));
    }

    public function show(Payment $payment)
    {
        $payment->load(['user', 'tickets.event']);

        $topup = WalletTopup::where('payment_id', $payment->id)->first();
        $morph = $payment->getMorphClass();

        $walletTransactions = WalletTransaction::query()
            ->where(function ($q) use ($payment, $morph, $topup) {
                $q->where(fn ($w) => $w->where('reference_type', $morph)->where('reference_id', $payment->id));
                if ($topup) {
                    $q->orWhere(fn ($w) => $w->where('reference_type', $topup->getMorphClass())->where('reference_id', $topup->id));
                }
            })
            ->orderBy('id')
            ->get();

        // Gateway records (docs/payments_thawani.md §4).
        $gateway = [
            'events' => PaymentEvent::with('actor')->where('payment_id', $payment->id)->orderBy('id')->get(),
            'sessions' => PaymentGatewaySession::where('payment_id', $payment->id)->latest('id')->get(),
            'calls' => PaymentGatewayCall::where('payment_id', $payment->id)->latest('id')->limit(100)->get(),
            'webhooks' => PaymentWebhookEvent::where('payment_id', $payment->id)->latest('id')->get(),
            'refund' => PaymentRefund::with('requester')->where('payment_id', $payment->id)->first(),
        ];

        return view('admin.payments.show', compact('payment', 'topup', 'walletTransactions', 'gateway'));
    }

    /** POST payments/{payment}/reconcile — ask the gateway now. */
    public function reconcile(Payment $payment, ThawaniCheckout $thawani, PaymentRecorder $recorder)
    {
        abort_unless($payment->gateway === ThawaniGateway::NAME, 404);
        $recorder->record($payment, 'check_requested', 'admin');

        try {
            $fresh = $thawani->reconcile($payment, 'admin', 'interactive');
        } catch (GatewayException $e) {
            return back()->with('error', __('تعذّر الاتصال ببوابة الدفع: :message', ['message' => $e->getMessage()]));
        }

        return back()->with('success', $fresh->status === $payment->status
            ? __('لم تتغير حالة الدفعة لدى البوابة.')
            : __('حُدّثت حالة الدفعة من البوابة.'));
    }

    /**
     * POST payments/{payment}/refund/retry — a failed refund, or one marked
     * for review after an unclear answer (only after checking the Thawani
     * portal that no refund went through).
     */
    public function retryRefund(Request $request, Payment $payment, PaymentRecorder $recorder)
    {
        $refund = PaymentRefund::where('payment_id', $payment->id)->firstOrFail();
        if (! in_array($refund->status, [PaymentRefund::FAILED, PaymentRefund::NEEDS_REVIEW], true)) {
            return back()->with('error', __('لا يمكن إعادة هذا الاسترداد الآن.'));
        }
        $request->validate(['confirm_checked' => [$refund->status === PaymentRefund::NEEDS_REVIEW ? 'accepted' : 'nullable']]);

        $refund->forceFill([
            'status' => PaymentRefund::PENDING,
            'last_error' => null,
            'data' => array_merge($refund->data ?? [], ['in_flight' => false, 'retried_by' => $request->user()->id]),
        ])->save();
        $recorder->record($payment, 'gateway_refund_retry', 'admin', ['refund_id' => $refund->id]);
        ProcessGatewayRefund::dispatch($refund->id);

        return back()->with('success', __('أُرسل طلب الاسترداد مجددًا.'));
    }

    /** POST payments/{payment}/review — a person checked it. */
    public function resolveReview(Request $request, Payment $payment, PaymentRecorder $recorder)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $reason = $payment->review_reason;
        $payment->forceFill(['needs_review' => false, 'review_reason' => null])->save();
        $recorder->record($payment, 'review_cleared', 'admin', array_filter(['reason' => $reason, 'note' => $data['note'] ?? null]));

        return back()->with('success', __('سُجّلت مراجعة الدفعة.'));
    }

    /** GET payments/gateway-log?tab=webhooks|calls */
    public function gatewayLog(Request $request, TenantContext $context)
    {
        $tab = $request->input('tab') === 'calls' ? 'calls' : 'webhooks';
        $tenantId = $context->id();
        // Webhooks that matched no payment have no tenant; platform admins see them.
        $platform = $request->user()->can('manage tenants');
        $scope = fn ($q) => $q->where(fn ($w) => $w->where('tenant_id', $tenantId)->when($platform, fn ($o) => $o->orWhereNull('tenant_id')));

        $filters = [
            'status' => (string) $request->input('status', ''),
            'operation' => (string) $request->input('operation', ''),
        ];

        $rows = $tab === 'webhooks'
            ? PaymentWebhookEvent::query()->tap($scope)
                ->when(isset(\App\Support\AdminLabels::WEBHOOK_STATUSES[$filters['status']]), fn ($q) => $q->where('status', $filters['status']))
                ->latest('id')->paginate(50)->withQueryString()
            : PaymentGatewayCall::query()->tap($scope)
                ->when(isset(\App\Support\AdminLabels::GATEWAY_CALL_OUTCOMES[$filters['status']]), fn ($q) => $q->where('outcome', $filters['status']))
                ->when($filters['operation'] !== '', fn ($q) => $q->where('operation', $filters['operation']))
                ->latest('id')->paginate(50)->withQueryString();

        $stats = [
            'webhooks_failed' => PaymentWebhookEvent::query()->tap($scope)->whereIn('status', ['failed', 'rejected'])->where('created_at', '>', now()->subDay())->count(),
            'calls_failed' => PaymentGatewayCall::query()->tap($scope)->whereIn('outcome', ['server_error', 'network_error', 'circuit_open'])->where('created_at', '>', now()->subDay())->count(),
            'calls_total' => PaymentGatewayCall::query()->tap($scope)->where('created_at', '>', now()->subDay())->count(),
            'avg_ms' => (int) PaymentGatewayCall::query()->tap($scope)->where('outcome', 'ok')->where('created_at', '>', now()->subDay())->avg('duration_ms'),
        ];

        return view('admin.payments.gateway-log', compact('tab', 'rows', 'filters', 'stats'));
    }

    /** POST payments/webhooks/{webhookEvent}/replay */
    public function replayWebhook(Request $request, PaymentWebhookEvent $webhookEvent, TenantContext $context, PaymentRecorder $recorder)
    {
        $platform = $request->user()->can('manage tenants');
        abort_unless($webhookEvent->tenant_id === $context->id() || ($platform && $webhookEvent->tenant_id === null), 404);
        abort_if($webhookEvent->status === PaymentWebhookEvent::REJECTED, 422, 'A webhook with an invalid signature is not replayed.');

        $webhookEvent->forceFill(['status' => PaymentWebhookEvent::RECEIVED, 'last_error' => null])->save();
        if ($webhookEvent->payment_id && ($payment = Payment::withoutTenantScope()->find($webhookEvent->payment_id))) {
            $recorder->record($payment, 'webhook_replayed', 'admin', ['webhook_event_id' => $webhookEvent->id]);
        }
        ProcessPaymentWebhook::dispatch($webhookEvent->id);

        return back()->with('success', __('أُعيدت معالجة الإشعار.'));
    }

    public function refund(Request $request, Payment $payment, WalletAdminService $admin)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $admin->refundPayment($payment, $request->user(), $data['reason'] ?? null);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = match (true) {
            $payment->method === Payment::METHOD_WALLET => 'استُردت الدفعة وأُعيد المبلغ إلى المحفظة',
            $payment->gateway === ThawaniGateway::NAME => 'استُردت الدفعة، وأُرسل طلب إعادة المبلغ إلى البطاقة عبر ثواني.',
            default => 'سُجِّلت الدفعة مستردة. أكمل الاسترداد عبر بوابة الدفع.',
        };

        return redirect()->route('admin.payments.show', $payment)->with('success', $message);
    }
}
