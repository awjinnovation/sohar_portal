<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GatePairingCode;
use App\Services\Gate\GatePairingService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Admin → Gates → Devices: paired scanners, pairing codes (QR) and revocation. */
class GateDeviceController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(Request $request)
    {
        $status = $request->input('status');
        $devices = GateDevice::with(['gate', 'staff:id,name'])
            ->when(in_array($status, ['active', 'revoked'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('gate'), fn ($q) => $q->where('gate_id', (int) $request->input('gate')))
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('last_seen_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $gates = Gate::orderBy('sort_order')->orderBy('id')->get();

        // Non-JS fallback: the code created by the last POST is shown in the modal.
        $pairing = null;
        if ($id = session('gate_pairing_code_id')) {
            $code = GatePairingCode::with('gate')->find($id);
            $pairing = $code ? $this->pairingPayload($code) : null;
        }

        return view('admin.gates.devices', compact('devices', 'gates', 'status', 'pairing'));
    }

    /** Create a 10-minute pairing code for a gate (JSON for the modal, redirect otherwise). */
    public function pair(Request $request, GatePairingService $pairing)
    {
        $data = $request->validate([
            'gate_id' => ['required', 'integer', Rule::exists('gates', 'id')->where('tenant_id', $this->context->id())],
        ]);
        $gate = Gate::findOrFail($data['gate_id']);
        $code = $pairing->createCode($gate, $request->user());
        $code->setRelation('gate', $gate);
        AuditLog::record('gate.pairing_code_created', $gate, ['code_id' => $code->id]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $this->pairingPayload($code)]);
        }

        return redirect()->route('admin.gates.devices.index')->with('gate_pairing_code_id', $code->id);
    }

    public function update(Request $request, GateDevice $device)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'gate_id' => ['nullable', 'integer', Rule::exists('gates', 'id')->where('tenant_id', $this->context->id())],
        ]);
        $device->forceFill(['name' => $data['name'], 'gate_id' => $data['gate_id'] ?? null])->save();
        AuditLog::record('gate.device_updated', $device, $data);

        return back()->with('success', 'حُدِّث الجهاز');
    }

    public function revoke(GateDevice $device)
    {
        if (! $device->isRevoked()) {
            $device->revoke();
            AuditLog::record('gate.device_revoked', $device, ['name' => $device->name]);
        }

        return back()->with('success', 'أُلغي الجهاز، ولن يمسح حتى يُقرَن من جديد.');
    }

    private function pairingPayload(GatePairingCode $code): array
    {
        $link = GatePairingService::deepLink($code, $this->context->get(), $this->apiBase());

        return [
            'code' => $code->code,
            'gate' => $code->gate?->localizedName(),
            'gate_id' => $code->gate_id,
            'expires_at' => $code->expires_at->toIso8601String(),
            'expires_in' => max(0, (int) now()->diffInSeconds($code->expires_at, false)),
            'used' => $code->used_at !== null,
            'link' => $link,
            'qr_svg' => (string) QrCode::format('svg')->size(240)->margin(1)->errorCorrection('M')->generate($link),
        ];
    }

    private function apiBase(): string
    {
        return rtrim(request()->getSchemeAndHttpHost(), '/');
    }
}
