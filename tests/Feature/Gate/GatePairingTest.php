<?php

namespace Tests\Feature\Gate;

use App\Models\GateDevice;
use App\Models\GatePairingCode;
use App\Services\Gate\GatePairingService;
use PHPUnit\Framework\Attributes\Test;

class GatePairingTest extends GateTestCase
{
    #[Test]
    public function a_pairing_code_is_exchanged_once_for_a_device_token(): void
    {
        $gate = $this->makeGate(['code' => 'A', 'name' => ['en' => 'Main Gate A', 'ar' => 'البوابة A']]);
        $code = app(GatePairingService::class)->createCode($gate);

        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/', $code->code);
        $this->assertEqualsWithDelta(10 * 60, now()->diffInSeconds($code->expires_at), 5);

        // lower case / spaced input is accepted; no X-Tenant needed (the code knows its tenant)
        $res = $this->postJson('/api/v1/gate/pair', [
            'pairing_code' => strtolower(substr($code->code, 0, 3).' '.substr($code->code, 3)),
            'device' => ['name' => 'Lane 1', 'device_type' => 'android_pos', 'model' => 'Sunmi V2s', 'platform' => 'Android 11', 'app_version' => '1.2.0'],
        ])->assertCreated()
            ->assertJsonPath('data.device.name', 'Lane 1')
            ->assertJsonPath('data.gate.code', 'A')
            ->assertJsonPath('data.gate.name.en', 'Main Gate A')
            ->assertJsonPath('data.tenant.slug', 'north-batinah');

        $device = GateDevice::findOrFail($res->json('data.device.id'));
        $this->assertSame($gate->id, $device->gate_id);
        $this->assertSame('android_pos', $device->device_type);
        $this->assertNotNull($code->fresh()->used_at);
        $this->assertSame($device->id, $code->fresh()->device_id);

        // the token is a Sanctum token of the device with the `gate` ability only
        $token = $device->tokens()->sole();
        $this->assertSame(['gate'], $token->abilities);
        $this->gate('GET', '/api/v1/gate/session', [], $res->json('data.device_token'))->assertOk()
            ->assertJsonPath('data.device.id', $device->id)
            ->assertJsonPath('data.gate.id', $gate->id);

        // single use
        $this->postJson('/api/v1/gate/pair', ['pairing_code' => $code->code])->assertStatus(410)->assertJsonPath('code', 'PAIRING_CODE_USED');
    }

    #[Test]
    public function expired_unknown_and_foreign_codes_are_refused(): void
    {
        $gate = $this->makeGate();
        $expired = app(GatePairingService::class)->createCode($gate);
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/gate/pair', ['pairing_code' => $expired->code])->assertStatus(410)->assertJsonPath('code', 'PAIRING_CODE_EXPIRED');
        $this->postJson('/api/v1/gate/pair', ['pairing_code' => 'ZZZZZZ'])->assertStatus(422)->assertJsonPath('code', 'PAIRING_CODE_INVALID');
        $this->postJson('/api/v1/gate/pair', ['pairing_code' => 'ABC'])->assertStatus(422)->assertJsonPath('code', 'PAIRING_CODE_INVALID');
        $this->postJson('/api/v1/gate/pair', [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');

        // a code of tenant A sent with X-Tenant of tenant B is not accepted
        $this->makeTenant('other');
        $fresh = app(GatePairingService::class)->createCode($gate);
        $this->postJson('/api/v1/gate/pair', ['pairing_code' => $fresh->code], ['X-Tenant' => 'other'])->assertStatus(422);
        $this->assertNull($fresh->fresh()->used_at);
        $this->assertSame(0, GateDevice::withoutTenantScope()->count());
    }

    #[Test]
    public function pairing_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/gate/pair', ['pairing_code' => 'QQQQQQ'])->assertStatus(422);
        }
        $this->postJson('/api/v1/gate/pair', ['pairing_code' => 'QQQQQQ'])->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_REQUESTS');
    }

    #[Test]
    public function the_artisan_command_prints_a_usable_code_and_deep_link(): void
    {
        $this->makeGate(['code' => 'A']);

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('gate:pair-code', ['tenant' => 'north-batinah', 'gate' => 'a', '--api' => 'http://10.0.0.5:8002']));
        $output = \Illuminate\Support\Facades\Artisan::output();

        $code = GatePairingCode::sole();
        $this->assertStringContainsString($code->code, $output);
        $this->assertStringContainsString('mawsim-gate://pair?code='.$code->code.'&tenant=north-batinah&api=http%3A%2F%2F10.0.0.5%3A8002', $output);
        $this->postJson('/api/v1/gate/pair', ['pairing_code' => $code->code])->assertCreated();

        $this->artisan('gate:pair-code', ['tenant' => 'nope', 'gate' => 'A'])->assertFailed();
        $this->artisan('gate:pair-code', ['tenant' => 'north-batinah', 'gate' => 'X9'])->assertFailed();
    }
}
