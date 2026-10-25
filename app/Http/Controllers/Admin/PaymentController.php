<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletAdminService;
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
            'q' => trim((string) $request->input('q')),
        ];

        $payments = Payment::with('user')
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('payment_type', $v))
            ->when($filters['method'], fn ($q, $v) => $q->where('method', $v))
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

        return view('admin.payments.show', compact('payment', 'topup', 'walletTransactions'));
    }

    public function refund(Request $request, Payment $payment, WalletAdminService $admin)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $admin->refundPayment($payment, $request->user(), $data['reason'] ?? null);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $payment->method === Payment::METHOD_WALLET
            ? 'استُردت الدفعة وأُعيد المبلغ إلى المحفظة'
            : 'سُجِّلت الدفعة مستردة. أكمل الاسترداد عبر بوابة الدفع.';

        return redirect()->route('admin.payments.show', $payment)->with('success', $message);
    }
}
