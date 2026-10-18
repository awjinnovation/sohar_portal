<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardMetrics;
use App\Tenancy\TenantContext;

class DashboardController extends Controller
{
    public function index(DashboardMetrics $metrics, TenantContext $context)
    {
        $tenant = $context->get();

        return view('admin.dashboard', [
            'stats' => $metrics->counts(),
            'walletStats' => $metrics->wallet($tenant),
            'm' => $metrics->activity($tenant),
        ]);
    }
}
