<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wallet\PointsTransactionResource;
use App\Models\PointsTransaction;
use App\Services\Wallet\PointsService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Loyalty points (contract §7).
 */
class PointsController extends Controller
{
    public function __construct(private TenantContext $context, private PointsService $points) {}

    public function show(Request $request)
    {
        $tenant = $this->context->get();
        $account = $this->points->accountFor($request->user(), $tenant);
        $info = $this->points->tierInfo($tenant, $account);
        $tier = fn (?array $t) => $t ? [
            'code' => $t['code'] ?? null,
            'name' => $this->localized($t['name'] ?? null),
            'multiplier' => (float) ($t['multiplier'] ?? 1),
            'color' => $t['color'] ?? null,
            'min_points' => (int) ($t['min_points'] ?? 0),
        ] : null;

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $account->balance,
                'lifetime_earned' => $account->lifetime_earned,
                'label' => $this->localized($tenant->setting('points.label')),
                'tier' => $tier($info['tier']),
                'next_tier' => $tier($info['next_tier']),
                'points_to_next_tier' => $info['points_to_next_tier'],
                'redeem_value_minor_per_point' => (int) $tenant->setting('points.redeem_value_minor_per_point'),
                'min_redeem_points' => (int) $tenant->setting('points.min_redeem_points'),
                'expiring' => $this->points->expiringSoon($account),
            ],
        ]);
    }

    public function history(Request $request)
    {
        $account = $this->points->accountFor($request->user(), $this->context->get());
        $page = PointsTransaction::where('points_account_id', $account->id)->orderByDesc('id')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => PointsTransactionResource::collection($page->items()),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** POST /points/redeem {points, idempotency_key} -> promo wallet credit */
    public function redeem(Request $request)
    {
        $data = $request->validate([
            'points' => 'required|integer|min:1',
            'idempotency_key' => 'required|string|max:100',
        ]);
        $tenant = $this->context->get();

        $result = $this->points->redeemToWallet($request->user(), $tenant, (int) $data['points'], $data['idempotency_key']);
        $wallet = $result['wallet']->wallet()->withoutGlobalScopes()->first();
        $account = $this->points->accountFor($request->user(), $tenant);

        return response()->json([
            'success' => true,
            'message' => 'Points redeemed',
            'data' => [
                'points_redeemed' => -$result['points']->points,
                'credited_minor' => $result['wallet']->amount_minor,
                'credited' => $tenant->formatMoney($result['wallet']->amount_minor),
                'wallet_balance_minor' => $wallet->balance_minor,
                'wallet_balance' => $tenant->formatMoney($wallet->balance_minor),
                'points_balance' => $account->balance,
            ],
        ]);
    }

    private function localized(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }

        return $v[app()->getLocale()] ?? $v['en'] ?? reset($v);
    }
}
