<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\GateDevice;
use App\Models\User;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateManifestService;
use App\Services\Gate\GatePairingService;
use App\Services\Gate\GateStaffAuthenticator;
use App\Services\Gate\GateStatsService;
use App\Services\Gate\ScanInput;
use App\Services\Gate\ScanOutcome;
use App\Services\Gate\ScanResult;
use App\Services\Gate\TicketScanService;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gate scanner API (docs/ai_and_gate_contract.md §B2), /api/v1/gate/*.
 * Every route except /pair is authenticated as a GateDevice
 * (App\Http\Middleware\AuthenticateGateDevice).
 */
class GateController extends Controller
{
    public function __construct(
        private TenantContext $context,
        private TicketScanService $scanner,
        private GateManifestService $manifest,
        private GateStatsService $stats,
    ) {}

    /** POST /gate/pair (no auth) */
    public function pair(Request $request, GatePairingService $pairing): JsonResponse
    {
        $data = $request->validate([
            'pairing_code' => ['required', 'string', 'max:20'],
            'device' => ['nullable', 'array'],
            'device.name' => ['nullable', 'string', 'max:100'],
            'device.device_type' => ['nullable', 'string', Rule::in(GateDevice::TYPES)],
            'device.model' => ['nullable', 'string', 'max:100'],
            'device.platform' => ['nullable', 'string', 'max:40'],
            'device.app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $header = trim((string) $request->header(config('tenancy.header', 'X-Tenant'), ''));
        $paired = $pairing->pair($data['pairing_code'], $data['device'] ?? [], $header !== '' ? $header : null, $request->ip());
        $tenant = $paired['tenant'];

        return response()->json([
            'success' => true,
            'message' => 'Device paired',
            'data' => [
                'device_token' => $paired['token'],
                'device' => ['id' => $paired['device']->id, 'name' => $paired['device']->name],
                'gate' => $paired['gate']?->toApi(),
                'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            ],
        ], 201);
    }

    /** GET /gate/session */
    public function session(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $tenant = $this->context->get();
        $gate = $device->gate;
        $date = GateDay::today($tenant);
        $locale = app()->getLocale();

        $events = Event::active()
            ->when($gate?->event_id, fn ($q) => $q->whereKey($gate->event_id))
            ->whereDate('start_time', '<=', $date)->whereDate('end_time', '>=', $date)
            ->orderBy('start_time')->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $locale === 'ar' ? ($e->title_ar ?: $e->title) : $e->title,
                'title_i18n' => ['en' => $e->title, 'ar' => $e->title_ar ?: $e->title],
                'start' => TenantTime::iso($e->start_time, $tenant),
                'end' => TenantTime::iso($e->end_time, $tenant),
            ])->values();

        return $this->ok([
            'device' => $device->toApi() + ['scanner_source' => $device->scanner_source],
            'gate' => $gate?->toApi(),
            'staff' => $device->staff ? ['id' => $device->staff->id, 'name' => $device->staff->name] : null,
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name, 'timezone' => TenantTime::zone($tenant)],
            'date' => $date,
            'events_today' => $events,
            'settings' => [
                'allow_reentry' => (bool) $gate?->allow_reentry,
                'offline_allowed' => (bool) $tenant->setting('gate.offline_allowed', true),
                'beep' => (bool) $tenant->setting('gate.beep', true),
                'require_staff_login' => (bool) $tenant->setting('gate.require_staff_login', false),
                'direction' => $gate?->direction ?? 'entry',
            ],
            'manifest_version' => $this->manifest->version($tenant, $date, $gate),
            'server_time' => TenantTime::iso(Carbon::now(), $tenant),
        ]);
    }

    /** POST /gate/staff/login {phone, otp} */
    public function staffLogin(Request $request, GateStaffAuthenticator $auth): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'size:6'],
        ]);
        $user = $auth->login($this->device($request), $data['phone'], $data['otp']);

        return $this->ok(['staff' => ['id' => $user->id, 'name' => $user->name]]);
    }

    /** POST /gate/staff/logout */
    public function staffLogout(Request $request, GateStaffAuthenticator $auth): JsonResponse
    {
        $auth->logout($this->device($request));

        return $this->ok(['ok' => true]);
    }

    /** POST /gate/scan */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', Rule::in(['camera', 'hardware', 'wedge', 'manual'])],
            'direction' => ['nullable', 'string', Rule::in(['in', 'out', 'entry', 'exit'])],
            'scanned_at' => ['nullable', 'date'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $device = $this->device($request);
        $gate = $this->activeGate($device);
        $staff = $this->staff($device);

        $outcome = $this->scanner->scan(new ScanInput(
            code: $data['code'],
            gate: $gate,
            device: $device,
            staff: $staff,
            source: $data['source'] ?? 'camera',
            direction: $data['direction'] ?? null,
            scannedAt: isset($data['scanned_at']) ? Carbon::parse($data['scanned_at']) : null,
            idempotencyKey: $data['idempotency_key'],
        ));
        $this->rememberSource($device, $data['source'] ?? null);

        return $this->ok($this->scanPayload($outcome, $device));
    }

    /** GET /gate/manifest?date=YYYY-MM-DD (ETag / If-None-Match) */
    public function manifest(Request $request)
    {
        $device = $this->device($request);
        $tenant = $this->context->get();
        $date = GateDay::parse($request->query('date'), $tenant);
        $gate = $device->gate;

        $version = $this->manifest->version($tenant, $date, $gate);
        $etag = '"'.$version.'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache'];

        $sent = array_map(fn ($t) => trim(str_replace('W/', '', $t)), explode(',', (string) $request->header('If-None-Match', '')));
        if (in_array($etag, $sent, true)) {
            return response('', 304, $headers);
        }

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $this->manifest->build($tenant, $date, $gate)], 200, $headers);
    }

    /** POST /gate/sync {scans: [...]} (max 500, idempotent per key) */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scans' => ['required', 'array', 'min:1', 'max:500'],
            'scans.*.code_hash' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'scans.*.result_local' => ['required', 'string', Rule::in(ScanResult::ALL)],
            'scans.*.source' => ['nullable', 'string', Rule::in(['camera', 'hardware', 'wedge', 'manual'])],
            'scans.*.direction' => ['nullable', 'string', Rule::in(['in', 'out', 'entry', 'exit'])],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.idempotency_key' => ['required', 'string', 'max:100', 'distinct'],
        ]);
        $device = $this->device($request);
        $gate = $device->gate;
        $staff = $this->staff($device);

        $this->manifest->resetCache();
        // Oldest first, so the first admission wins and later duplicates are the conflicts.
        $scans = collect($data['scans'])->sortBy(fn ($s) => Carbon::parse($s['scanned_at'])->getTimestamp())->values();

        $results = [];
        foreach ($scans as $item) {
            try {
                $outcome = $this->scanner->scan(new ScanInput(
                    codeHash: $item['code_hash'],
                    gate: $gate,
                    device: $device,
                    staff: $staff,
                    source: $item['source'] ?? 'camera',
                    direction: $item['direction'] ?? null,
                    scannedAt: Carbon::parse($item['scanned_at']),
                    idempotencyKey: $item['idempotency_key'],
                    offline: true,
                    resultLocal: $item['result_local'],
                ));
                $results[$item['idempotency_key']] = [
                    'idempotency_key' => $item['idempotency_key'],
                    'result' => $outcome->result,
                    'conflict' => $outcome->conflict,
                ];
            } catch (ApiException $e) {
                $results[$item['idempotency_key']] = [
                    'idempotency_key' => $item['idempotency_key'],
                    'result' => null,
                    'conflict' => false,
                    'error' => $e->errorCode,
                ];
            }
        }
        if ($last = $scans->last()) {
            $this->rememberSource($device, $last['source'] ?? null);
        }

        // Same order as sent.
        $ordered = array_values(array_map(fn ($s) => $results[$s['idempotency_key']], $data['scans']));

        return $this->ok([
            'results' => $ordered,
            'counts' => $this->stats->counts($this->context->get(), $gate, $device),
        ]);
    }

    /** GET /gate/stats?date= */
    public function stats(Request $request): JsonResponse
    {
        $tenant = $this->context->get();

        return $this->ok($this->stats->deviceStats($tenant, $this->device($request), GateDay::parse($request->query('date'), $tenant)));
    }

    /** POST /gate/heartbeat {app_version, battery?, queue_size} */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_version' => ['nullable', 'string', 'max:40'],
            'battery' => ['nullable', 'integer', 'between:0,100'],
            'queue_size' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'scanner_source' => ['nullable', 'string', Rule::in(['camera', 'hardware', 'wedge', 'manual'])],
        ]);
        $device = $this->device($request);
        $tenant = $this->context->get();

        $device->forceFill(array_filter([
            'app_version' => $data['app_version'] ?? null,
            'battery' => $data['battery'] ?? null,
            'queue_size' => $data['queue_size'] ?? null,
            'scanner_source' => $data['scanner_source'] ?? null,
        ], fn ($v) => $v !== null) + ['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();

        return $this->ok([
            'server_time' => TenantTime::iso(Carbon::now(), $tenant),
            'manifest_version' => $this->manifest->version($tenant, GateDay::today($tenant), $device->gate),
            'device_status' => $device->status,
        ]);
    }

    // ------------------------------------------------------------------

    private function scanPayload(ScanOutcome $o, GateDevice $device): array
    {
        $tenant = $this->context->get();
        $t = $o->ticket;
        $locale = app()->getLocale();
        $event = $t?->event;

        return [
            'result' => $o->result,
            'admit' => $o->admitted(),
            'direction' => $o->direction,
            'message' => $o->message(),
            'ticket' => $t ? [
                'number' => $t->ticket_number,
                'holder_name' => $t->holder_name ?? $t->user?->name,
                'type' => $t->ticket_type,
                'event_id' => $t->event_id,
                'event_title' => $event ? ($locale === 'ar' ? ($event->title_ar ?: $event->title) : $event->title) : null,
                'valid_date' => $t->booking_date?->toDateString(),
                'used_at' => TenantTime::iso($t->used_at, $tenant),
                'quantity' => (int) ($t->quantity ?: 1),
                'quantity_label' => trans_choice('gate.people', (int) ($t->quantity ?: 1), ['count' => (int) ($t->quantity ?: 1)]),
                'check_in_count' => (int) $t->check_in_count,
                'max_check_ins' => (int) ($t->max_check_ins ?: 1),
            ] : null,
            'scan_id' => $o->scan?->id,
            'replay' => $o->replay,
            'counts' => $this->stats->counts($tenant, $device->gate, $device),
        ];
    }

    private function device(Request $request): GateDevice
    {
        return $request->attributes->get('gate_device');
    }

    private function activeGate(GateDevice $device): ?\App\Models\Gate
    {
        $gate = $device->gate;
        if ($gate && ! $gate->active) {
            throw new ApiException('GATE_INACTIVE', __('gate.errors.gate_inactive'), 409);
        }

        return $gate;
    }

    private function staff(GateDevice $device): ?User
    {
        $staff = $device->staff_user_id ? $device->staff : null;
        if (! $staff && $this->context->get()->setting('gate.require_staff_login', false)) {
            throw new ApiException('STAFF_LOGIN_REQUIRED', __('gate.errors.not_staff'), 403);
        }

        return $staff;
    }

    private function rememberSource(GateDevice $device, ?string $source): void
    {
        if ($source && $device->scanner_source !== $source) {
            $device->forceFill(['scanner_source' => $source])->saveQuietly();
        }
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }
}
