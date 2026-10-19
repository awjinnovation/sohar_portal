<?php

namespace App\Services\Gate;

use App\Exceptions\ApiException;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\GatePairingCode;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Device pairing (contract §B2 `POST /gate/pair`).
 *
 * An admin creates a single-use 6-character code for a gate (valid 10 min);
 * the scanner app sends it once and receives a device token — a Sanctum
 * personal access token of the GateDevice with the ability `gate`. The
 * plain token is only returned at pairing time.
 */
class GatePairingService
{
    public const TOKEN_ABILITY = 'gate';

    public function __construct(private TenantContext $context) {}

    public function createCode(Gate $gate, ?User $admin = null, ?int $minutes = null): GatePairingCode
    {
        $minutes ??= GatePairingCode::TTL_MINUTES;

        // Housekeeping: drop this tenant's codes that expired more than a day ago.
        GatePairingCode::where('expires_at', '<', now()->subDay())->delete();

        for ($try = 1; ; $try++) {
            try {
                $code = new GatePairingCode([
                    'gate_id' => $gate->id,
                    'code' => self::randomCode(),
                    'expires_at' => now()->addMinutes(max(1, $minutes)),
                    'created_by' => $admin?->id,
                ]);
                $code->tenant_id = $gate->tenant_id;
                $code->save();

                return $code;
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }

    public static function randomCode(int $length = 6): string
    {
        $alphabet = GatePairingCode::ALPHABET;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    /**
     * Deep link encoded in the pairing QR. `api` is the server root as seen
     * by the admin's browser (APP_URL in the console) — the app appends
     * /api/v1, like its API_BASE_URL build setting.
     */
    public static function deepLink(GatePairingCode $code, Tenant $tenant, ?string $apiBase = null): string
    {
        return 'mawsim-gate://pair?'.http_build_query([
            'code' => $code->code,
            'tenant' => $tenant->slug,
            'api' => rtrim($apiBase ?: url('/'), '/'),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange a pairing code for a device token.
     *
     * @param  string|null  $tenantSlug  X-Tenant sent by the app (if any): must match the code's tenant
     * @return array{device: GateDevice, gate: ?Gate, tenant: Tenant, token: string}
     */
    public function pair(string $rawCode, array $device, ?string $tenantSlug, ?string $ip = null): array
    {
        $codeValue = GatePairingCode::normalize($rawCode);

        return DB::transaction(function () use ($codeValue, $device, $tenantSlug, $ip) {
            $code = strlen($codeValue) === 6
                ? GatePairingCode::withoutTenantScope()->where('code', $codeValue)->lockForUpdate()->first()
                : null;
            $tenant = $code ? Tenant::find($code->tenant_id) : null;

            if (! $code || ! $tenant || ! $tenant->isActive() || ($tenantSlug && strtolower($tenantSlug) !== $tenant->slug)) {
                throw new ApiException('PAIRING_CODE_INVALID', __('gate.errors.pairing_code_invalid'), 422);
            }
            if ($code->used_at) {
                throw new ApiException('PAIRING_CODE_USED', __('gate.errors.pairing_code_used'), 410);
            }
            if ($code->expires_at->isPast()) {
                throw new ApiException('PAIRING_CODE_EXPIRED', __('gate.errors.pairing_code_expired'), 410);
            }

            // The device belongs to the code's tenant, whatever X-Tenant said.
            $this->context->set($tenant);

            $model = new GateDevice([
                'gate_id' => $code->gate_id,
                'name' => trim((string) ($device['name'] ?? '')) ?: 'Scanner '.$code->code,
                'device_type' => in_array($device['device_type'] ?? null, GateDevice::TYPES, true) ? $device['device_type'] : 'phone',
                'model' => $device['model'] ?? null,
                'platform' => $device['platform'] ?? null,
                'app_version' => $device['app_version'] ?? null,
                'status' => GateDevice::STATUS_ACTIVE,
                'paired_at' => now(),
                'last_seen_at' => now(),
            ]);
            $model->tenant_id = $tenant->id;
            $model->last_ip = $ip;
            $model->save();

            $code->forceFill(['used_at' => now(), 'device_id' => $model->id])->save();

            $token = $model->createToken('gate-device', [self::TOKEN_ABILITY])->plainTextToken;

            return ['device' => $model, 'gate' => Gate::find($code->gate_id), 'tenant' => $tenant, 'token' => $token];
        });
    }
}
