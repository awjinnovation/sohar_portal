<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\PointsAccount;
use App\Models\PointsTransaction;
use App\Services\Wallet\PointsService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Loyalty points of the current tenant: accounts (balance / tier /
 * lifetime), history, manual adjustments (reason + audit) and the rules
 * summary (settings live in Branding & settings → Points).
 */
class PointsController extends Controller
{
    public function __construct(private TenantContext $context, private PointsService $points)
    {
    }

    public function index(Request $request)
    {
        $tenant = $this->context->get();
        $q = trim((string) $request->input('q'));
        $tiers = collect($tenant->setting('points.tiers', []))->sortBy('min_points')->values();
        $tierCode = (string) $request->input('tier');
        $range = $tierCode !== '' ? $this->tierRange($tiers, $tierCode) : null;

        $accounts = PointsAccount::with('user')
            ->when($q !== '', fn ($query) => $query->whereHas('user', function ($u) use ($q) {
                $u->where(fn ($w) => $w->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%")
                    ->orWhere('phone_number', 'like', "%$q%"));
            }))
            ->when($range !== null, function ($query) use ($range) {
                $query->where('lifetime_earned', '>=', $range[0]);
                if ($range[1] !== null) {
                    $query->where('lifetime_earned', '<', $range[1]);
                }
            })
            ->orderByDesc('balance')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $outstanding = (int) PointsAccount::sum('balance');
        $totals = [
            'accounts' => PointsAccount::count(),
            'outstanding' => $outstanding,
            'liability_minor' => $outstanding * (int) $tenant->setting('points.redeem_value_minor_per_point', 0),
            'lifetime' => (int) PointsAccount::sum('lifetime_earned'),
            'expiring_30d' => (int) PointsTransaction::where('remaining', '>', 0)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->sum('remaining'),
        ];

        return view('admin.points.index', [
            'accounts' => $accounts,
            'q' => $q,
            'tierCode' => $tierCode,
            'tiers' => $tiers,
            'totals' => $totals,
            'rules' => $tenant->setting('points', []),
            'moduleEnabled' => $tenant->moduleEnabled('points'),
            'tierOf' => fn (PointsAccount $a) => $this->points->tierInfo($tenant, $a),
        ]);
    }

    public function show(PointsAccount $account)
    {
        $account->load('user');
        $tenant = $this->context->get();
        $history = PointsTransaction::where('points_account_id', $account->id)->latest('id')->paginate(25);

        return view('admin.points.show', [
            'account' => $account,
            'history' => $history,
            'tier' => $this->points->tierInfo($tenant, $account),
            'expiring' => $this->points->expiringSoon($account, 60),
            'redeemValue' => (int) $tenant->setting('points.redeem_value_minor_per_point', 0),
        ]);
    }

    public function adjust(Request $request, PointsAccount $account)
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1', 'max:10000000'],
            'direction' => ['required', 'in:credit,debit'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $signed = $data['direction'] === 'debit' ? -(int) $data['points'] : (int) $data['points'];

        try {
            $this->points->adminAdjust($account, $signed, $data['reason'], $request->user());
        } catch (ApiException $e) {
            return back()->withInput()->with('error', $e->errorCode === 'INSUFFICIENT_POINTS'
                ? __('رصيد النقاط غير كافٍ لهذا الخصم.')
                : $e->getMessage());
        }

        return redirect()->route('admin.points.show', $account)
            ->with('success', __('تم تعديل النقاط: :points', ['points' => ($signed > 0 ? '+' : '−').number_format(abs($signed))]));
    }

    /** [min lifetime, next tier min | null] of a tier code. */
    private function tierRange($tiers, string $code): ?array
    {
        $index = $tiers->search(fn ($t) => ($t['code'] ?? null) === $code);
        if ($index === false) {
            return null;
        }
        $next = $tiers->get($index + 1);

        return [(int) ($tiers[$index]['min_points'] ?? 0), $next ? (int) $next['min_points'] : null];
    }
}
