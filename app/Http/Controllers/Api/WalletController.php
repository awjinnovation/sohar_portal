<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wallet\DepositResource;
use App\Http\Resources\Wallet\RefundRequestResource;
use App\Http\Resources\Wallet\WalletResource;
use App\Http\Resources\Wallet\WalletTransactionResource;
use App\Models\Deposit;
use App\Models\RefundRequest;
use App\Models\WalletTransaction;
use App\Services\Wallet\PayTokenService;
use App\Services\Wallet\PointsService;
use App\Services\Wallet\RefundRequestService;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Wallet endpoints (contract §6) — always scoped to the authenticated user
 * and the X-Tenant tenant.
 */
class WalletController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private WalletService $wallets,
        private PointsService $points,
    ) {}

    public function show(Request $request)
    {
        $tenant = $this->context->get();
        $wallet = $this->wallets->walletFor($request->user(), $tenant);

        $points = null;
        if ($tenant->moduleEnabled('points')) {
            $account = $this->points->accountFor($request->user(), $tenant);
            $info = $this->points->tierInfo($tenant, $account);
            $points = [
                'balance' => $account->balance,
                'tier_code' => $info['tier']['code'] ?? null,
                'next_tier_code' => $info['next_tier']['code'] ?? null,
                'points_to_next_tier' => $info['points_to_next_tier'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => new WalletResource(['wallet' => $wallet, 'points' => $points]),
        ]);
    }

    public function transactions(Request $request)
    {
        $request->validate(['type' => 'nullable|string|in:'.implode(',', WalletTransaction::TYPES)]);
        $wallet = $this->wallets->walletFor($request->user(), $this->context->get());

        $page = WalletTransaction::with('vendor')
            ->where('wallet_id', $wallet->id)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'data' => WalletTransactionResource::collection($page->items()),
            'pagination' => $this->pagination($page),
        ]);
    }

    public function payToken(Request $request, PayTokenService $tokens)
    {
        $issued = $tokens->issue($request->user(), $this->context->get());

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $issued['token'],
                'expires_at' => \App\Support\TenantTime::iso($issued['expires_at']),
                'expires_in' => $issued['expires_in'],
            ],
        ]);
    }

    public function deposits(Request $request)
    {
        $wallet = $this->wallets->walletFor($request->user(), $this->context->get());

        return response()->json([
            'success' => true,
            'data' => DepositResource::collection(
                Deposit::with('vendor')->where('wallet_id', $wallet->id)->orderByDesc('id')->get()
            ),
        ]);
    }

    public function refundRequests(Request $request)
    {
        $wallet = $this->wallets->walletFor($request->user(), $this->context->get());

        return response()->json([
            'success' => true,
            'data' => RefundRequestResource::collection(
                RefundRequest::where('wallet_id', $wallet->id)->orderByDesc('id')->get()
            ),
        ]);
    }

    public function storeRefundRequest(Request $request, RefundRequestService $refunds)
    {
        $data = $request->validate([
            'amount_minor' => 'nullable|integer|min:1',
            'method' => 'required|string|in:original_payment,bank_transfer',
            'reason' => 'nullable|string|max:1000',
            'bank_iban' => 'nullable|string|max:64',
            'bank_name' => 'nullable|string|max:255',
            'idempotency_key' => 'required|string|max:100',
        ]);

        $refund = $refunds->create($request->user(), $this->context->get(), $data, $data['idempotency_key']);

        return response()->json([
            'success' => true,
            'message' => 'Refund request submitted',
            'data' => new RefundRequestResource($refund),
        ], $refund->wasRecentlyCreated ? 201 : 200);
    }

    private function pagination($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
