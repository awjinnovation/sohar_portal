<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PointsAccount;
use App\Models\PointsTransaction;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\PointsService;
use App\Services\Wallet\WalletAdminService;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private WalletService $wallets,
        private WalletAdminService $admin,
    ) {
    }

    public function index(Request $request)
    {
        // Legacy link (?user_id=): read-only, never creates a wallet.
        if ($request->filled('user_id')) {
            return redirect()->route('admin.wallets.user', (int) $request->input('user_id'));
        }

        $q = trim((string) $request->input('q'));
        $status = $request->input('status');
        $matchUser = function ($query) use ($q) {
            $query->where(function ($u) use ($q) {
                $u->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%")
                    ->orWhere('phone_number', 'like', "%$q%");
            });
        };

        $wallets = Wallet::with('user')
            ->when($q !== '', fn ($w) => $w->whereHas('user', $matchUser))
            ->when(in_array($status, [Wallet::STATUS_ACTIVE, Wallet::STATUS_FROZEN], true), fn ($w) => $w->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Users matching the search that have no wallet in this tenant yet.
        $usersWithoutWallet = $q === '' ? collect() : User::query()
            ->tap($matchUser)
            ->whereDoesntHave('wallets')
            ->orderBy('name')
            ->limit(10)
            ->get();

        $totals = [
            'balance' => (int) Wallet::sum('balance_minor'),
            'held' => (int) Wallet::sum('held_minor'),
            'count' => Wallet::count(),
            'frozen' => Wallet::where('status', Wallet::STATUS_FROZEN)->count(),
        ];

        return view('admin.wallets.index', compact('wallets', 'usersWithoutWallet', 'q', 'status', 'totals'));
    }

    /**
     * The wallet of a user in the current tenant. GET never mutates: a user
     * without a wallet gets a "no wallet yet" page; the wallet is only
     * created by POST (createForUser).
     */
    public function forUser(User $user)
    {
        $wallet = Wallet::where('user_id', $user->id)->first();
        if ($wallet) {
            return redirect()->route('admin.wallets.show', $wallet);
        }

        return view('admin.wallets.no-wallet', ['user' => $user]);
    }

    public function createForUser(User $user)
    {
        $existed = Wallet::where('user_id', $user->id)->exists();
        $wallet = $this->wallets->walletFor($user, $this->context->get());
        if (! $existed) {
            AuditLog::record('wallet.created', $wallet, ['user_id' => $user->id]);
        }

        return redirect()->route('admin.wallets.show', $wallet)
            ->with('success', $existed ? null : 'تم إنشاء المحفظة');
    }

    public function show(Wallet $wallet, PointsService $points)
    {
        $wallet->load('user');

        $transactions = WalletTransaction::with('vendor')
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        // Vendor charges on this page that were already refunded.
        $chargeIds = $transactions->getCollection()->where('type', 'vendor_payment')->pluck('id');
        $refundedChargeIds = $chargeIds->isEmpty() ? [] : WalletTransaction::where('type', 'refund')
            ->where('reference_type', (new WalletTransaction)->getMorphClass())
            ->whereIn('reference_id', $chargeIds)
            ->pluck('reference_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deposits = $wallet->deposits()->with('vendor')->latest('id')->limit(50)->get();
        $refundRequests = RefundRequest::where('wallet_id', $wallet->id)->latest('id')->limit(50)->get();

        $pointsAccount = PointsAccount::where('user_id', $wallet->user_id)->first();
        $tier = $pointsAccount ? $points->tierInfo($this->context->get(), $pointsAccount) : null;
        $pointsTransactions = $pointsAccount
            ? PointsTransaction::where('points_account_id', $pointsAccount->id)->latest('id')->limit(20)->get()
            : collect();

        return view('admin.wallets.show', compact(
            'wallet', 'transactions', 'refundedChargeIds', 'deposits', 'refundRequests',
            'pointsAccount', 'tier', 'pointsTransactions'
        ));
    }

    public function adjust(Request $request, Wallet $wallet)
    {
        $tenant = $this->context->get();
        $decimals = $tenant->currencyDecimals();

        $data = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,'.max(1, $decimals).'})?$/'],
            'direction' => ['required', 'in:credit,debit'],
            'bucket' => ['required', 'in:cash,promo'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'amount.regex' => "المبلغ يجب أن يكون رقمًا موجبًا بحد أقصى $decimals منازل عشرية.",
        ]);

        $minor = $tenant->toMinor($data['amount']);
        if ($minor <= 0) {
            throw ValidationException::withMessages(['amount' => 'المبلغ يجب أن يكون أكبر من صفر.']);
        }
        $signed = $data['direction'] === 'debit' ? -$minor : $minor;

        try {
            $this->admin->adjust($wallet, $signed, $data['bucket'], $data['reason'], $request->user());
        } catch (ApiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.wallets.show', $wallet)
            ->with('success', 'تم تنفيذ التعديل: '.($signed > 0 ? '+' : '-').$tenant->formatMoney($minor).' '.$tenant->currency());
    }

    public function status(Request $request, Wallet $wallet)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.Wallet::STATUS_ACTIVE.','.Wallet::STATUS_FROZEN],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $from = $wallet->status;
        $this->wallets->setStatus($wallet, $data['status']);
        AuditLog::record('wallet.status', $wallet, ['from' => $from, 'to' => $data['status'], 'reason' => $data['reason'] ?? null]);

        return redirect()->route('admin.wallets.show', $wallet)
            ->with('success', $data['status'] === Wallet::STATUS_FROZEN ? 'تم تجميد المحفظة' : 'تم تفعيل المحفظة');
    }

    public function refundCharge(Request $request, WalletTransaction $transaction)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->admin->refundVendorCharge($transaction, $request->user(), $data['reason'] ?? null);
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.wallets.show', $transaction->wallet_id)
            ->with('success', 'تم استرداد عملية الدفع إلى المحفظة');
    }
}
