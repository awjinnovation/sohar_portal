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
        ->where('created_at', '<', now()->subMinutes((int) $this->option('minutes')))
        ->orderBy('id')
        ->each(function (Payment $payment) use ($fulfillment, &$count) {
            $fulfillment->fail($payment, 'cancelled');
            $count++;
        });
    $this->info("Cancelled {$count} abandoned payments.");
})->purpose('Cancel abandoned gateway checkouts and give back reserved points');

Schedule::command('points:expire')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('wallet:expire-promo')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('payments:expire-pending')->hourly()->withoutOverlapping();
