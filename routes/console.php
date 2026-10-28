<?php

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Models\WalletPromoLot;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Wallet\PointsService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('points:expire {--tenant= : Only this tenant slug}', function (PointsService $points) {
    $tenant = $this->option('tenant') ? Tenant::where('slug', $this->option('tenant'))->firstOrFail() : null;
    $expired = $points->expire($tenant);
    $this->info("Expired {$expired} points.");
})->purpose('Expire unspent loyalty points whose expires_at has passed');

Artisan::command('wallet:expire-promo {--tenant= : Only this tenant slug}', function (WalletService $wallets) {
    $tenantId = $this->option('tenant') ? Tenant::where('slug', $this->option('tenant'))->firstOrFail()->id : null;
    $count = 0;
    $total = 0;
    WalletPromoLot::withoutTenantScope()
        ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
        ->where('remaining_minor', '>', 0)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->distinct()
        ->pluck('wallet_id')
        ->each(function ($walletId) use ($wallets, &$count, &$total) {
            $wallet = Wallet::withoutTenantScope()->find($walletId);
            if ($wallet && ($tx = $wallets->expirePromo($wallet))) {
                $count++;
                $total += $tx->amount_minor;
            }
        });
    $this->info("Expired promo credit in {$count} wallets ({$total} minor units).");
})->purpose('Expire promo wallet credit older than wallet.promo_expiry_days (FIFO)');

Artisan::command('payments:expire-pending {--minutes=120}', function (PaymentFulfillmentService $fulfillment) {
    $count = 0;
    Payment::withoutTenantScope()
        ->where('status', 'pending')
        ->where('method', Payment::METHOD_GATEWAY)
        // Thawani payments are checked with Thawani first (payments:reconcile).
        ->where(fn ($q) => $q->whereNull('gateway')->orWhere('gateway', '!=', 'thawani'))
        ->where('created_at', '<', now()->subMinutes((int) $this->option('minutes')))
        ->orderBy('id')
        ->each(function (Payment $payment) use ($fulfillment, &$count) {
            $fulfillment->fail($payment, 'cancelled', [], 'scheduler', ['reason' => 'abandoned']);
            $count++;
        });
    $this->info("Cancelled {$count} abandoned payments.");
})->purpose('Cancel abandoned Bank Muscat / fake gateway checkouts and give back reserved points');

/*
| Thawani reconciliation (docs/payments_thawani.md §6), every minute:
|  1. pending payments due for a check (next_check_at) are checked with
|     Thawani; past payments.reconcile.give_up_after_minutes the check is
|     final and an unpaid payment is cancelled;
|  2. pending payments whose link was never opened (no session) are
|     cancelled after the same time, without a gateway call;
|  3. sessions still open at Thawani after their payment was cancelled
|     (late capture) or paid through another session (double charge) are
|     checked every 30 minutes until they expire, and once after;
|  4. webhooks still `received` after 5 minutes and refunds still `pending`
|     after 2 hours (lost jobs) are queued again.
*/
Artisan::command('payments:reconcile {--limit=}', function (PaymentFulfillmentService $fulfillment) {
    $batch = max(1, (int) ($this->option('limit') ?: config('payments.reconcile.batch', 500)));
    $giveUp = now()->subMinutes((int) config('payments.reconcile.give_up_after_minutes', 120));
    $counts = ['checks' => 0, 'abandoned' => 0, 'late' => 0, 'webhooks' => 0, 'refunds' => 0];

    Payment::withoutTenantScope()
        ->whereIn('status', ['pending', 'processing'])
        ->where('method', Payment::METHOD_GATEWAY)
        ->where('gateway', 'thawani')
        ->whereNotNull('gateway_session_id')
        // next_check_at is null once a person must look (e.g. amount mismatch).
        ->where('next_check_at', '<=', now())
        ->orderBy('next_check_at')
        ->limit($batch)
        ->get(['id', 'created_at'])
        ->each(function (Payment $p) use ($giveUp, &$counts) {
            \App\Jobs\ReconcilePayment::dispatch($p->id, $p->created_at->lt($giveUp));
            $counts['checks']++;
        });

    Payment::withoutTenantScope()
        ->where('status', 'pending')
        ->where('method', Payment::METHOD_GATEWAY)
        ->where('gateway', 'thawani')
        ->whereNull('gateway_session_id')
        ->where('created_at', '<', $giveUp)
        ->orderBy('id')
        ->limit($batch)
        ->get()
        ->each(function (Payment $p) use ($fulfillment, &$counts) {
            $fulfillment->fail($p, 'cancelled', [], 'scheduler', ['reason' => 'payment_page_never_opened']);
            $counts['abandoned']++;
        });

    \App\Models\PaymentGatewaySession::query()
        ->join('payments', 'payments.id', '=', 'payment_gateway_sessions.payment_id')
        ->where('payment_gateway_sessions.gateway', 'thawani')
        ->where('payment_gateway_sessions.status', 'open')
        ->where(fn ($q) => $q->whereIn('payments.status', ['cancelled', 'failed'])
            ->orWhere(fn ($w) => $w->whereIn('payments.status', ['completed', 'refunded'])
                ->whereColumn('payment_gateway_sessions.session_id', '!=', 'payments.gateway_session_id')))
        // Still payable, or expired but not checked since: one last look.
        ->where(fn ($q) => $q->where('payment_gateway_sessions.expires_at', '>', now()->subMinutes(10))
            ->orWhere(fn ($w) => $w->whereNull('payment_gateway_sessions.expires_at')->where('payment_gateway_sessions.created_at', '>', now()->subDays(2)))
            ->orWhereNull('payment_gateway_sessions.last_checked_at')
            ->orWhereColumn('payment_gateway_sessions.last_checked_at', '<', 'payment_gateway_sessions.expires_at'))
        ->where(fn ($q) => $q->whereNull('payment_gateway_sessions.last_checked_at')->orWhere('payment_gateway_sessions.last_checked_at', '<', now()->subMinutes(30)))
        ->limit($batch)
        ->distinct()
        ->pluck('payment_gateway_sessions.payment_id')
        ->each(function ($paymentId) use (&$counts) {
            \App\Jobs\ReconcilePayment::dispatch((int) $paymentId, false, 'reconciler');
            $counts['late']++;
        });

    \App\Models\PaymentWebhookEvent::where('status', 'received')
        ->where('updated_at', '<', now()->subMinutes(5))
        ->limit($batch)
        ->pluck('id')
        ->each(function ($id) use (&$counts) {
            \App\Jobs\ProcessPaymentWebhook::dispatch((int) $id);
            $counts['webhooks']++;
        });

    \App\Models\PaymentRefund::where('status', 'pending')
        ->where('updated_at', '<', now()->subHours(2))
        ->limit($batch)
        ->pluck('id')
        ->each(function ($id) use (&$counts) {
            \App\Jobs\ProcessGatewayRefund::dispatch((int) $id);
            $counts['refunds']++;
        });

    $this->info(collect($counts)->map(fn ($n, $k) => "$k: $n")->implode(', '));
})->purpose('Check pending Thawani payments with Thawani; cancel unpaid ones after the give-up time');

Artisan::command('payments:prune-gateway-logs {--days=}', function () {
    $cutoff = now()->subDays(max(30, (int) ($this->option('days') ?: config('payments.log_retention_days', 400))));
    $calls = 0;
    $hooks = 0;
    do {
        $ids = \App\Models\PaymentGatewayCall::where('created_at', '<', $cutoff)->orderBy('id')->limit(5000)->pluck('id');
        $calls += $ids->isEmpty() ? 0 : \App\Models\PaymentGatewayCall::whereIn('id', $ids)->delete();
    } while ($ids->count() === 5000);
    do {
        // Rejected deliveries (bad signature, unknown tenant…) go after 30 days.
        $ids = \App\Models\PaymentWebhookEvent::where(fn ($q) => $q->where(fn ($w) => $w->where('created_at', '<', $cutoff)->whereIn('status', ['processed', 'ignored']))
            ->orWhere(fn ($w) => $w->where('created_at', '<', now()->subDays(30))->where('status', 'rejected')))
            ->orderBy('id')->limit(5000)->pluck('id');
        $hooks += $ids->isEmpty() ? 0 : \App\Models\PaymentWebhookEvent::whereIn('id', $ids)->delete();
    } while ($ids->count() === 5000);
    $this->info("Pruned {$calls} gateway calls and {$hooks} webhook events older than {$cutoff->toDateString()}.");
})->purpose('Delete raw gateway call and webhook logs past payments.log_retention_days (the payment timeline is kept)');

Schedule::command('points:expire')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('wallet:expire-promo')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('payments:expire-pending')->hourly()->withoutOverlapping();
// Overlap locks expire (minutes): a scheduler killed mid-run (deploy) must
// not stop reconciliation for the default 24 hours.
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command('payments:prune-gateway-logs')->dailyAt('03:30')->withoutOverlapping(120)->onOneServer();

// AI Experience: concierge conversations expire 30 days after the last
// message, shared plans after ai.plan_ttl_days (docs/ai_and_gate_contract.md §A3).
Artisan::command('ai:prune', function () {
    $conversations = \App\Models\AiConversation::withoutTenantScope()
        ->where('updated_at', '<', now()->subDays((int) config('ai.conversation_ttl_days', 30)))
        ->delete();
    $plans = \App\Models\AiPlan::withoutTenantScope()
        ->where('created_at', '<', now()->subDays((int) config('ai.plan_ttl_days', 90)))
        ->delete();
    $this->info("Pruned {$conversations} AI conversations and {$plans} plans.");
})->purpose('Delete expired AI concierge conversations and old visit plans');

Schedule::command('ai:prune')->dailyAt('03:10')->withoutOverlapping();

// Gate box office (docs/gate_sales_contract.md §2): unpaid orders expire
// after gate_sales.link_ttl_minutes and give their seats back.
Artisan::command('box-office:expire', function (\App\Services\Gate\BoxOfficeService $boxOffice) {
    $count = $boxOffice->expireDue();
    $this->info("Expired {$count} box office orders.");
})->purpose('Expire unpaid gate box office orders and release their seats');

Schedule::command('box-office:expire')->everyMinute()->withoutOverlapping();
