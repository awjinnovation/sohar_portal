<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Wallet\DepositResource;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use App\Services\Wallet\DepositService;
use App\Services\Wallet\MerchantService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Vendor / staff endpoints (contract §9). Guarded by the `merchant`
 * middleware which resolves the acting vendor.
 */
class MerchantController extends Controller
{
    public function __construct(private TenantContext $context, private MerchantService $merchant) {}

    private function vendor(Request $request): ?Vendor
    {
        return $request->attributes->get('vendor');
    }

    public function me(Request $request)
    {
        $vendor = $this->vendor($request);
        $user = $request->user();
        $permissions = $user->isSuperAdmin()
            ? ['charge wallets', 'manage deposits', 'validate tickets']
            : $user->getAllPermissions()->pluck('name')->values()->all();
        if ($user->hasRole('admin')) {
            $permissions = array_values(array_unique(array_merge($permissions, ['charge wallets', 'manage deposits', 'validate tickets'])));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'vendor' => $vendor ? [
                    'id' => $vendor->id,
                    'name' => $vendor->localizedName(),
                    'logo_url' => $vendor->logoUrl(),
                ] : null,
                'role' => $request->attributes->get('merchant_role'),
                'permissions' => $permissions,
                'vendors' => Vendor::where('is_active', true)
                    ->whereHas('staff', fn ($q) => $q->where('users.id', $user->id))
                    ->get()->map(fn ($v) => ['id' => $v->id, 'name' => $v->localizedName()])->values(),
            ],
        ]);
    }

    public function charge(Request $request)
    {
        $data = $request->validate([
            'pay_token' => 'required|string|max:512',
            'amount_minor' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
            'idempotency_key' => 'required|string|max:100',
        ]);
        $tenant = $this->context->get();
        if (! $tenant->moduleEnabled('vendors') || ! $tenant->moduleEnabled('wallet')) {
            throw ApiException::moduleDisabled('vendors');
        }
        $vendor = $this->vendor($request);
        if (! $vendor) {
            return response()->json(['success' => false, 'message' => 'No vendor assigned to this account', 'code' => 'FORBIDDEN'], 403);
        }

        $tx = $this->merchant->charge($request->user(), $tenant, $vendor, $data['pay_token'], (int) $data['amount_minor'],
            $data['description'] ?? null, $data['idempotency_key']);
        $customer = User::find($tx->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Payment completed',
            'data' => [
                'transaction_id' => $tx->id,
                'amount_minor' => $tx->amount_minor,
                'amount' => $tenant->formatMoney($tx->amount_minor),
                'customer_display_name' => $customer?->maskedDisplayName(),
                'status' => $tx->status,
                'created_at' => \App\Support\TenantTime::iso($tx->created_at),
            ],
        ]);
    }

    public function deposit(Request $request)
    {
        $data = $request->validate([
            'pay_token' => 'required|string|max:512',
            'type' => 'required|string|in:'.implode(',', Deposit::TYPES),
            'amount_minor' => 'required|integer|min:1',
            'label' => 'nullable|string|max:255',
            'idempotency_key' => 'required|string|max:100',
        ]);
        $tenant = $this->context->get();
        if (! $tenant->moduleEnabled('deposits') || ! $tenant->moduleEnabled('wallet')) {
            throw ApiException::moduleDisabled('deposits');
        }

        $deposit = $this->merchant->takeDeposit($request->user(), $tenant, $this->vendor($request), $data['pay_token'],
            $data['type'], (int) $data['amount_minor'], $data['label'] ?? null, $data['idempotency_key']);

        return response()->json(['success' => true, 'message' => 'Deposit held', 'data' => new DepositResource($deposit->load('vendor', 'user'))]);
    }

    public function releaseDeposit(Request $request, int $id, DepositService $deposits)
    {
        $data = $request->validate(['forfeit' => 'nullable|boolean']);
        $vendor = $this->vendor($request);

        $deposit = Deposit::query()
            // vendor staff may only release their vendor's deposits; gate staff any
            ->when($request->attributes->get('merchant_role') === 'vendor-staff', fn ($q) => $q->where('vendor_id', $vendor?->id))
            ->findOrFail($id);

        $deposit = $deposits->release($deposit, (bool) ($data['forfeit'] ?? false), $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Deposit '.$deposit->status, 'data' => new DepositResource($deposit->load('vendor', 'user'))]);
    }

    public function transactions(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $tenant = $this->context->get();
        $vendor = $this->vendor($request);
        $date = $request->input('date', now()->timezone((string) $tenant->setting('tenant.timezone', 'UTC'))->format('Y-m-d'));

        $query = WalletTransaction::query()
            ->whereIn('type', ['vendor_payment', 'deposit_hold', 'deposit_release', 'deposit_forfeit', 'refund'])
            ->whereDate('created_at', $date)
            ->when($vendor, fn ($q) => $q->where('vendor_id', $vendor->id), fn ($q) => $q->where('created_by', $request->user()->id))
            ->orderByDesc('id');

        $rows = $query->limit(500)->get();
        $sales = $rows->where('type', 'vendor_payment')->sum('amount_minor');
        $refunds = $rows->where('type', 'refund')->sum('amount_minor');

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'vendor' => $vendor ? ['id' => $vendor->id, 'name' => $vendor->localizedName()] : null,
                'transactions' => $rows->map(fn ($tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount_minor' => $tx->amount_minor,
                    'amount' => $tenant->formatMoney($tx->amount_minor),
                    'description' => $tx->meta['description'] ?? $tx->meta['label'] ?? null,
                    'customer_display_name' => User::find($tx->user_id)?->maskedDisplayName(),
                    'created_at' => \App\Support\TenantTime::iso($tx->created_at),
                ])->values(),
                'totals' => [
                    'count' => $rows->where('type', 'vendor_payment')->count(),
                    'sales_minor' => (int) $sales,
                    'sales' => $tenant->formatMoney((int) $sales),
                    'refunds_minor' => (int) $refunds,
                    'net_minor' => (int) ($sales - $refunds),
                    'net' => $tenant->formatMoney((int) ($sales - $refunds)),
                ],
            ],
        ]);
    }
}
