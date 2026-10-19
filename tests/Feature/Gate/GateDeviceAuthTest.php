<?php

namespace Tests\Feature\Gate;

use PHPUnit\Framework\Attributes\Test;

class GateDeviceAuthTest extends GateTestCase
{
    #[Test]
    public function gate_routes_need_a_device_token(): void
    {
        $this->getJson('/api/v1/gate/session')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->gate('GET', '/api/v1/gate/session', [], 'garbage')->assertUnauthorized();

        // a visitor / staff user token is not a device token
        $user = $this->makeUser();
        $userToken = $user->createToken('mobile-app')->plainTextToken;
        $this->gate('GET', '/api/v1/gate/session', [], $userToken)->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function device_tokens_are_refused_on_user_routes(): void
    {
        [, $token] = $this->pairDevice();

        $this->json('GET', '/api/v1/auth/profile', [], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->assertUnauthorized();
        $this->json('POST', '/api/v1/tickets/validate', ['qr_code' => 'x'], ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    #[Test]
    public function a_revoked_device_gets_device_revoked(): void
    {
        [$device, $token] = $this->pairDevice();
        $this->gate('GET', '/api/v1/gate/session', [], $token)->assertOk();

        $device->revoke();

        foreach ([['GET', '/api/v1/gate/session'], ['POST', '/api/v1/gate/heartbeat'], ['GET', '/api/v1/gate/manifest']] as [$m, $uri]) {
            $this->gate($m, $uri, [], $token)->assertUnauthorized()->assertJsonPath('code', 'DEVICE_REVOKED');
        }
        $this->scan($token, 'TKT-X')->assertUnauthorized()->assertJsonPath('code', 'DEVICE_REVOKED');
    }

    #[Test]
    public function the_device_tenant_wins_and_a_conflicting_header_is_refused(): void
    {
        $other = $this->makeTenant('other');
        [$device, $token] = $this->pairDevice(null, $other);

        // no header: the device's tenant is used (not the default tenant)
        $this->gate('GET', '/api/v1/gate/session', [], $token)->assertOk()->assertJsonPath('data.tenant.slug', 'other');
        $this->gate('GET', '/api/v1/gate/session', [], $token, ['X-Tenant' => 'other'])->assertOk();
        $this->gate('GET', '/api/v1/gate/session', [], $token, ['X-Tenant' => 'north-batinah'])->assertForbidden()->assertJsonPath('code', 'TENANT_MISMATCH');
    }

    #[Test]
    public function the_staff_scanner_module_must_be_enabled(): void
    {
        [, $token] = $this->pairDevice();
        $this->tenant->putSetting('modules.staff_scanner', false)->save();

        $this->gate('GET', '/api/v1/gate/session', [], $token)->assertForbidden()->assertJsonPath('code', 'MODULE_DISABLED');
    }

    #[Test]
    public function staff_log_in_on_the_device_with_the_otp_flow(): void
    {
        $this->seedRoles();
        config(['auth.otp.test_code' => '123456']);
        [$device, $token] = $this->pairDevice();
        $staff = $this->makeUser('96890000001');
        $this->grantRole($staff, 'staff');
        $visitor = $this->makeUser('96891234567');

        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000001'], ['X-Tenant' => 'north-batinah'])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000001', 'otp' => '000000'], $token)->assertUnauthorized()->assertJsonPath('code', 'OTP_INVALID');
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000001', 'otp' => '123456'], $token)->assertOk()
            ->assertJsonPath('data.staff.id', $staff->id);
        $this->assertSame($staff->id, $device->fresh()->staff_user_id);
        // the OTP is single use
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000001', 'otp' => '123456'], $token)->assertUnauthorized();

        // scans are attributed to the staff member
        $ticket = $this->makeTicket();
        $this->scan($token, $ticket->qr_code)->assertOk()->assertJsonPath('data.result', 'valid');
        $this->assertSame($staff->id, \App\Models\GateScan::sole()->staff_user_id);
        $this->assertStringContainsString('#'.$staff->id, $ticket->fresh()->validated_by);
        $this->gate('GET', '/api/v1/gate/session', [], $token)->assertJsonPath('data.staff.id', $staff->id);

        $this->gate('POST', '/api/v1/gate/staff/logout', [], $token)->assertOk()->assertJsonPath('data.ok', true);
        $this->assertNull($device->fresh()->staff_user_id);

        // a visitor without the staff role cannot log in on the gate
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96891234567'], ['X-Tenant' => 'north-batinah'])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96891234567', 'otp' => '123456'], $token)->assertForbidden()->assertJsonPath('code', 'NOT_STAFF');
        $this->assertNotNull($visitor);

        // vendor staff qualify once they hold the `validate tickets` permission in this tenant
        $cashier = $this->makeUser('96890000002');
        $this->grantRole($cashier, 'vendor-staff');
        $this->travel(3)->minutes();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000002'], ['X-Tenant' => 'north-batinah'])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000002', 'otp' => '123456'], $token)->assertForbidden();
        $this->inTenant($this->tenant, fn () => $cashier->givePermissionTo('validate tickets'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->travel(3)->minutes();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000002'], ['X-Tenant' => 'north-batinah'])->assertOk();
        $this->gate('POST', '/api/v1/gate/staff/login', ['phone' => '96890000002', 'otp' => '123456'], $token)->assertOk();
    }

    #[Test]
    public function staff_login_can_be_required_by_the_tenant(): void
    {
        $this->tenant->putSetting('gate.require_staff_login', true)->save();
        [, $token] = $this->pairDevice();
        $ticket = $this->makeTicket();

        $this->scan($token, $ticket->qr_code)->assertForbidden()->assertJsonPath('code', 'STAFF_LOGIN_REQUIRED');
        $this->gate('GET', '/api/v1/gate/session', [], $token)->assertJsonPath('data.settings.require_staff_login', true);
    }
}
