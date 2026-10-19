<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GateScan;
use App\Services\Gate\GateDay;
use App\Services\Gate\ScanResult;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/** Admin → Gates → Scan log with filters. */
class GateScanController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(Request $request)
    {
        $tenant = $this->context->get();
        $date = $request->filled('date') ? GateDay::parse($request->input('date'), $tenant) : null;
        $q = trim((string) $request->input('q'));

        $scans = GateScan::with(['gate', 'device:id,name', 'staff:id,name', 'ticket:id,ticket_number,ticket_type,holder_name,event_id', 'ticket.event:id,title,title_ar'])
            ->when($date, function ($query) use ($date, $tenant) {
                [$from, $to] = GateDay::window($date, $tenant);
                $query->where('scanned_at', '>=', $from)->where('scanned_at', '<', $to);
            })
            ->when($request->filled('gate'), fn ($query) => $request->input('gate') === 'none'
                ? $query->whereNull('gate_id') : $query->where('gate_id', (int) $request->input('gate')))
            ->when($request->filled('device'), fn ($query) => $query->where('device_id', (int) $request->input('device')))
            ->when(in_array($request->input('result'), ScanResult::ALL, true), fn ($query) => $query->where('result', $request->input('result')))
            ->when($request->input('result') === 'rejects', fn ($query) => $query->whereIn('result', ScanResult::REJECTS))
            ->when(in_array($request->input('direction'), ['in', 'out'], true), fn ($query) => $query->where('direction', $request->input('direction')))
            ->when(in_array($request->input('source'), ['camera', 'hardware', 'wedge', 'manual'], true), fn ($query) => $query->where('source', $request->input('source')))
            ->when($request->input('flag') === 'offline', fn ($query) => $query->where('offline', true))
            ->when($request->input('flag') === 'conflict', fn ($query) => $query->where('conflict', true))
            ->when($q !== '', fn ($query) => $query->whereHas('ticket', fn ($t) => $t->where('ticket_number', 'like', '%'.strtoupper($q).'%')))
            ->orderByDesc('scanned_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('admin.gates.scans', [
            'scans' => $scans,
            'gates' => Gate::orderBy('sort_order')->orderBy('id')->get(),
            'devices' => GateDevice::orderBy('name')->get(['id', 'name', 'tenant_id']),
            'date' => $date,
            'q' => $q,
        ]);
    }
}
