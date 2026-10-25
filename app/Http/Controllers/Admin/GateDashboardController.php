<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateStatsService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/** Admin → Gates → Live dashboard (polls dashboard/data every 15 s). */
class GateDashboardController extends Controller
{
    public function __construct(private TenantContext $context, private GateStatsService $stats) {}

    public function index(Request $request)
    {
        $tenant = $this->context->get();
        $date = GateDay::parse($request->query('date'), $tenant);

        return view('admin.gates.dashboard', [
            'date' => $date,
            'today' => GateDay::today($tenant),
            'payload' => $this->stats->dashboard($tenant, $date),
        ]);
    }

    public function data(Request $request)
    {
        $tenant = $this->context->get();

        return response()->json(['success' => true, 'data' => $this->stats->dashboard($tenant, GateDay::parse($request->query('date'), $tenant))]);
    }
}
