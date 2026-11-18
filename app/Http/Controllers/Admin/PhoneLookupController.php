<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use App\Models\BoxOfficeOrder;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Everything a visitor has in this tenant, found by phone number: the
 * account, its tickets, its payments and its gate box office orders.
 *
 * A full number in any format (91234567, +968 9123 4567, 0096891234567)
 * matches exactly; 4 or more digits match every number containing them.
 * Users are global, so an account is shown only when it has tickets,
 * payments or orders in this tenant (the models are tenant-scoped). Each
 * section follows the viewer's permissions.
 */
class PhoneLookupController extends Controller
{
    use TenantValidation;

    /** Rows per section (the latest first); totals are counted in full. */
    public const LIMIT = 50;

    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor?->canAny(['manage tickets', 'view payments', 'manage gates']), 403);

        $can = [
            'tickets' => $actor->can('manage tickets'),
            'payments' => $actor->can('view payments'),
            'orders' => $actor->can('manage gates'),
        ];

        $input = trim((string) $request->query('phone', ''));
        $digits = (string) preg_replace('/\D/', '', $input);
        $exact = $input !== '' ? PhoneNumber::normalize($this->currentTenant(), $input) : null;
        $searched = $exact !== null || strlen($digits) >= 4;
        $tooShort = $input !== '' && ! $searched;

        $data = [
            'accounts' => collect(), 'tickets' => collect(), 'payments' => collect(), 'orders' => collect(),
            'totals' => ['tickets' => 0, 'payments' => 0, 'orders' => 0, 'paid_minor' => 0],
        ];

        if ($searched) {
            $userIds = User::query()
                ->when($exact !== null,
                    fn ($q) => $q->where('phone_number', $exact),
                    fn ($q) => $q->where('phone_number', 'like', '%'.$digits.'%'))
                ->limit(200)
                ->pluck('id');

            $data = $this->lookup($userIds, $exact, $digits, $can);
        }

        return view('admin.phone-lookup.index', $data + [
            'phone' => $input,
            'exact' => $exact,
            'searched' => $searched,
            'tooShort' => $tooShort,
            'can' => $can,
            'limit' => self::LIMIT,
        ]);
    }

    /** Tenant-scoped records of [userIds] (box office orders also by the phone typed). */
    private function lookup(Collection $userIds, ?string $exact, string $digits, array $can): array
    {
        $tickets = Ticket::query()->whereIn('user_id', $userIds);
        $payments = Payment::query()->whereIn('user_id', $userIds);
        $orders = BoxOfficeOrder::query()->where(function ($q) use ($userIds, $exact, $digits) {
            $q->whereIn('buyer_user_id', $userIds)
                ->when($exact !== null,
                    fn ($w) => $w->orWhere('buyer_phone', $exact),
                    fn ($w) => $w->orWhere('buyer_phone', 'like', '%'.$digits.'%'));
        });

        // The accounts that have something in this tenant, in the sections this viewer may see.
        $owners = collect()
            ->merge($can['tickets'] ? (clone $tickets)->distinct()->pluck('user_id') : [])
            ->merge($can['payments'] ? (clone $payments)->distinct()->pluck('user_id') : [])
            ->merge($can['orders'] ? (clone $orders)->whereNotNull('buyer_user_id')->distinct()->pluck('buyer_user_id') : [])
            ->filter()->unique();

        $count = fn ($query, string $column) => (clone $query)->selectRaw("$column as owner, count(*) as total")
            ->groupBy($column)->pluck('total', 'owner');
        $ticketCounts = $can['tickets'] ? $count($tickets, 'user_id') : collect();
        $paymentCounts = $can['payments'] ? $count($payments, 'user_id') : collect();
        $orderCounts = $can['orders'] ? $count((clone $orders)->whereNotNull('buyer_user_id'), 'buyer_user_id') : collect();

        $accounts = User::query()->whereIn('id', $owners)->orderBy('name')->get(['id', 'name', 'phone_number', 'email', 'created_at'])
            ->map(fn (User $u) => [
                'user' => $u,
                'tickets' => (int) ($ticketCounts[$u->id] ?? 0),
                'payments' => (int) ($paymentCounts[$u->id] ?? 0),
                'orders' => (int) ($orderCounts[$u->id] ?? 0),
            ]);

        return [
            'accounts' => $accounts,
            'tickets' => $can['tickets']
                ? (clone $tickets)->with(['event', 'user', 'ticketType'])->latest('id')->limit(self::LIMIT)->get()
                : collect(),
            'payments' => $can['payments']
                ? (clone $payments)->with('user')->latest('id')->limit(self::LIMIT)->get()
                : collect(),
            'orders' => $can['orders']
                ? (clone $orders)->with(['gate', 'event'])->latest('id')->limit(self::LIMIT)->get()
                : collect(),
            'totals' => [
                'tickets' => $can['tickets'] ? (clone $tickets)->count() : 0,
                'payments' => $can['payments'] ? (clone $payments)->count() : 0,
                'orders' => $can['orders'] ? (clone $orders)->count() : 0,
                'paid_minor' => $can['payments'] ? (int) (clone $payments)->where('status', 'completed')->sum('amount_minor') : 0,
            ],
        ];
    }
}
