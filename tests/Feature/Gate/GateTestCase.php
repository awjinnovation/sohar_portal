<?php

namespace Tests\Feature\Gate;

use App\Models\Event;
use App\Models\Gate;
use App\Models\GateDevice;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\Gate\GateDay;
use App\Services\Gate\GatePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

abstract class GateTestCase extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    protected function makeGate(array $attrs = [], ?Tenant $tenant = null): Gate
    {
        return $this->inTenant($tenant ?? $this->tenant, fn () => Gate::create(array_merge([
            'name' => ['en' => 'Main Gate', 'ar' => 'البوابة الرئيسية'],
            'code' => 'G'.Str::upper(Str::random(4)),
            'direction' => 'entry',
            'allow_reentry' => false,
            'active' => true,
        ], $attrs)));
    }

    protected function makeTicket(?Event $event = null, array $attrs = [], ?Tenant $tenant = null): Ticket
    {
        $tenant ??= $this->tenant;
        $event ??= $this->makeEvent($tenant);

        return $this->inTenant($tenant, fn () => Ticket::create(array_merge([
            'event_id' => $event->id, 'user_id' => $this->makeUser()->id, 'ticket_type' => 'standard', 'status' => 'active',
            'price' => '1.000', 'currency' => 'OMR', 'holder_name' => 'Salim Al Harthy', 'purchase_date' => now(),
            'booking_date' => GateDay::today($tenant), 'quantity' => 1,
        ], $attrs)))->refresh();
    }

    /** @return array{0: GateDevice, 1: string} device + plain device token */
    protected function pairDevice(?Gate $gate = null, ?Tenant $tenant = null, string $name = 'Lane 1'): array
    {
        $tenant ??= $this->tenant;
        $gate ??= $this->makeGate([], $tenant);
        $code = $this->inTenant($tenant, fn () => app(GatePairingService::class)->createCode($gate));

        $res = $this->postJson('/api/v1/gate/pair', [
            'pairing_code' => $code->code,
            'device' => ['name' => $name, 'device_type' => 'android_pos', 'model' => 'Sunmi V2s', 'platform' => 'Android 11', 'app_version' => '1.0.0'],
        ], ['X-Tenant' => $tenant->slug])->assertCreated();
        $this->actingAsTenant($this->tenant);

        return [GateDevice::withoutTenantScope()->findOrFail($res->json('data.device.id')), $res->json('data.device_token')];
    }

    protected function gate(string $method, string $uri, array $data, string $token, array $headers = [])
    {
        $res = $this->json($method, $uri, $data, array_merge(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'], $headers));
        $this->actingAsTenant($this->tenant);

        return $res;
    }

    protected function scan(string $token, string $code, array $extra = [])
    {
        return $this->gate('POST', '/api/v1/gate/scan', array_merge([
            'code' => $code, 'source' => 'camera', 'scanned_at' => now()->toIso8601String(), 'idempotency_key' => (string) Str::uuid(),
        ], $extra), $token);
    }
}
