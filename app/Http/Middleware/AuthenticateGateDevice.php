<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\GateDevice;
use App\Models\Tenant;
use App\Services\Gate\GatePairingService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a paired gate scanner (routes under /api/v1/gate).
 *
 * Accepts only Sanctum tokens whose tokenable is a GateDevice and that carry
 * the `gate` ability — user tokens get 401 here, and device tokens never
 * pass `auth:sanctum` (that guard is bound to the users provider). The
 * device's tenant becomes the request tenant; an explicit X-Tenant header
 * naming another tenant is refused. Revoked devices get 401 DEVICE_REVOKED.
 *
 * The device is available as $request->attributes->get('gate_device').
 */
class AuthenticateGateDevice
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? PersonalAccessToken::findToken($plain) : null;

        if (! $token
            || $token->tokenable_type !== (new GateDevice)->getMorphClass()
            || ! $token->can(GatePairingService::TOKEN_ABILITY)
            || ($token->expires_at && $token->expires_at->isPast())) {
            throw new ApiException('UNAUTHENTICATED', __('gate.errors.unauthenticated'), 401);
        }

        $device = GateDevice::withoutTenantScope()->find($token->tokenable_id);
        if (! $device) {
            throw new ApiException('UNAUTHENTICATED', __('gate.errors.unauthenticated'), 401);
        }
        if ($device->isRevoked()) {
            throw new ApiException('DEVICE_REVOKED', __('gate.errors.device_revoked'), 401);
        }

        $tenant = Tenant::find($device->tenant_id);
        if (! $tenant || ! $tenant->isActive()) {
            throw new ApiException('TENANT_NOT_FOUND', __('platform.errors.tenant_not_found'), 404);
        }
        $header = trim((string) $request->header(config('tenancy.header', 'X-Tenant'), ''));
        if ($header !== '' && strtolower($header) !== $tenant->slug) {
            throw new ApiException('TENANT_MISMATCH', __('gate.errors.tenant_mismatch'), 403);
        }

        $this->context->set($tenant);
        $locale = strtolower(substr((string) $request->header('Accept-Language', ''), 0, 2));
        $locales = (array) $tenant->setting('tenant.locales', ['ar', 'en']);
        app()->setLocale(in_array($locale, $locales, true) ? $locale : (string) $tenant->setting('tenant.default_locale', 'en'));

        $device->setRelation('tenant', $tenant);
        $device->withAccessToken($token);

        // Presence: at most one write per minute per device (heartbeat writes explicitly).
        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('gate_device', $device);

        return $next($request);
    }
}
