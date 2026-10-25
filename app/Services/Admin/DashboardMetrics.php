<?php

namespace App\Services\Admin;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Deposit;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\Restaurant;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only figures for the admin dashboard of the current tenant (every
 * model used here except User is tenant-scoped). Called by
 * Admin\DashboardController.
 */
class DashboardMetrics
{
    /** Entity counts per area. */
    public function counts(): array
    {
        return [
            'main' => [
                'users' => User::count(),
                'events' => Event::count(),
                'tickets' => Ticket::count(),
                'payments' => Payment::count(),
            ],
            'catalog' => [
                'categories' => Category::count(),
                'event_tags' => EventTag::count(),
                'restaurants' => Restaurant::count(),
            ],
            'locations' => [
                'all_locations' => MapLocation::count(),
                'emergency' => MapLocation::where('type', 'emergency')->count(),
                'services' => MapLocation::whereIn('type', ['parking', 'restroom', 'facilities'])->count(),
                'first_aid' => MapLocation::where('type', 'first_aid')->count(),
            ],
            'communication' => [
                'announcements' => Announcement::count(),
                'notifications' => Notification::count(),
            ],
        ];
    }

    /** Money overview (minor units). */
    public function wallet(?Tenant $tenant): array
    {
        $tz = (string) ($tenant?->setting('tenant.timezone') ?: config('app.timezone'));
        $todayStart = Carbon::now($tz)->startOfDay()->setTimezone(config('app.timezone'));

        return [
            'balances' => (int) Wallet::sum('balance_minor'),
            'pending_refunds' => RefundRequest::where('status', 'pending')->count(),
            'held_deposits' => (int) Deposit::where('status', 'held')->sum('amount_minor'),
            'vendor_sales_today' => (int) WalletTransaction::where('type', 'vendor_payment')
                ->where('direction', 'debit')
                ->where('created_at', '>=', $todayStart)
                ->sum('amount_minor'),
        ];
    }

    /** KPIs, 14-day series and recent activity. */
    public function activity(?Tenant $tenant): array
    {
        $tz = (string) ($tenant?->setting('tenant.timezone') ?: config('app.timezone'));
        $appTz = config('app.timezone');
        $today = Carbon::now($tz)->startOfDay()->setTimezone($appTz);
        $yesterday = $today->copy()->subDay();
        $days = 14;
        $from = Carbon::now($tz)->startOfDay()->subDays($days - 1)->setTimezone($appTz);

        $safe = function (callable $fn, $fallback = 0) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                report($e);

                return $fallback;
            }
        };

        $completed = fn () => Payment::where('status', 'completed');

        $m = [
            'revenue_today' => $safe(fn () => (int) $completed()->where('created_at', '>=', $today)->sum('amount_minor')),
            'revenue_yesterday' => $safe(fn () => (int) $completed()->whereBetween('created_at', [$yesterday, $today])->sum('amount_minor')),
            'tickets_today' => $safe(fn () => Ticket::where('created_at', '>=', $today)->count()),
            'tickets_total' => $safe(fn () => Ticket::count()),
            'tickets_used' => $safe(fn () => Ticket::where('status', 'used')->count()),
            'topups_today' => $safe(fn () => (int) WalletTransaction::where('type', 'topup')->where('direction', 'credit')
                ->where('status', 'completed')->where('created_at', '>=', $today)->sum('amount_minor')),
            'topups_count_today' => $safe(fn () => WalletTransaction::where('type', 'topup')->where('direction', 'credit')
                ->where('created_at', '>=', $today)->count()),
            'refunds_pending' => $safe(fn () => RefundRequest::where('status', 'pending')->count()),
            'refunds_pending_minor' => $safe(fn () => (int) RefundRequest::where('status', 'pending')->sum('amount_minor')),
            'refunds_approved' => $safe(fn () => RefundRequest::where('status', 'approved')->count()),
            'vendors_active' => $safe(fn () => Vendor::where('is_active', true)->count()),
            'vendors_total' => $safe(fn () => Vendor::count()),
        ];

        // Daily series for the last $days days (tenant-local dates).
        $labels = [];
        $revenue = [];
        $topups = [];
        $tickets = [];
        $paymentRows = $safe(fn () => $completed()->where('created_at', '>=', $from)->get(['created_at', 'amount_minor', 'payment_type']), collect());
        $ticketRows = $safe(fn () => Ticket::where('created_at', '>=', $from)->get(['created_at']), collect());
        for ($i = 0; $i < $days; $i++) {
            $day = Carbon::now($tz)->startOfDay()->subDays($days - 1 - $i);
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $match = fn ($row) => Carbon::parse($row->created_at)->setTimezone($tz)->toDateString() === $key;
            $dayPayments = $paymentRows->filter($match);
            $revenue[] = (int) $dayPayments->where('payment_type', Payment::TYPE_TICKET)->sum('amount_minor');
            $topups[] = (int) $dayPayments->where('payment_type', Payment::TYPE_TOPUP)->sum('amount_minor');
            $tickets[] = $ticketRows->filter($match)->count();
        }
        $m['series'] = ['labels' => $labels, 'ticket_revenue' => $revenue, 'topups' => $topups, 'tickets' => $tickets];

        $m['payment_status'] = $safe(fn () => Payment::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all(), []);

        $m['recent'] = $safe(fn () => WalletTransaction::with(['user', 'vendor'])->latest('id')->limit(8)->get(), collect());
        $m['recent_payments'] = $safe(fn () => Payment::with('user')->latest('id')->limit(6)->get(), collect());
        $m['pending_refunds_list'] = $safe(fn () => RefundRequest::with('user')->where('status', 'pending')->latest('id')->limit(4)->get(), collect());

        $m['has_vendor_column'] = Schema::hasColumn('wallet_transactions', 'vendor_id');

        return $m;
    }
}
