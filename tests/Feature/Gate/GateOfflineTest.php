<?php

namespace Tests\Feature\Gate;

use App\Models\GateScan;
use App\Services\Gate\GateDay;
use App\Services\Gate\GateManifestService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/** Manifest (salted hashes, ETag), offline sync with conflict detection, stats and heartbeat. */
class GateOfflineTest extends GateTestCase
{
    private function syncItem(string $hash, string $resultLocal = 'valid', array $extra = []): array
    {
        return array_merge([
            'code_hash' => $hash, 'result_local' => $resultLocal, 'source' => 'camera', 'direction' => 'in',
            'scanned_at' => now()->subMinutes(5)->toIso8601String(), 'idempotency_key' => (string) Str::uuid(),
        ], $extra);
    }

    #[Test]
    public function the_manifest_lists_todays_tickets_of_the_gate_as_salted_hashes(): void
    {
        $event = $this->makeEvent();
        $other = $this->makeEvent(null, ['title' => 'Other']);
        [, $token] = $this->pairDevice($this->makeGate(['event_id' => $event->id]));
        $active = $this->makeTicket($event, ['ticket_type' => 'vip']);
        $used = $this->makeTicket($event, ['status' => 'used', 'check_in_count' => 1]);
        $cancelled = $this->makeTicket($event, ['status' => 'cancelled']);
        $open = $this->makeTicket($event, ['booking_date' => null]);           // no date: event runs today
        $this->makeTicket($event, ['booking_date' => GateDay::dateFor(now()->addDays(2))]); // other day
        $this->makeTicket($other);                                              // other event
        $this->makeTicket(null, [], $this->makeTenant('other'));                 // other tenant

        $res = $this->gate('GET', '/api/v1/gate/manifest', [], $token)->assertOk();
        $data = $res->json('data');
        $this->assertSame(GateDay::today(), $data['date']);
        $this->assertSame(4, $data['count']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $data['salt']);
        $this->assertSame('"'.$data['version'].'"', $res->headers->get('ETag'));

        $byHash = collect($data['tickets'])->keyBy('h');
        $h = fn ($t) => hash('sha256', $data['salt'].$t->qr_code);
        $this->assertSame(['h' => $h($active), 's' => 'active', 't' => 'vip', 'e' => $event->id, 'd' => GateDay::today()], $byHash[$h($active)]);
        $this->assertSame('used', $byHash[$h($used)]['s']);
        $this->assertSame('cancelled', $byHash[$h($cancelled)]['s']);
        $this->assertSame(GateDay::today(), $byHash[$h($open)]['d']);
        // no raw codes in the payload
        $this->assertStringNotContainsString($active->qr_code, $res->getContent());
        $this->assertStringNotContainsString($active->ticket_number, $res->getContent());

        // ETag: unchanged → 304, a scan changes the version
        $this->gate('GET', '/api/v1/gate/manifest', [], $token, ['If-None-Match' => $res->headers->get('ETag')])->assertStatus(304);
        $this->travel(2)->seconds();
        $this->scan($token, $active->qr_code)->assertJsonPath('data.result', 'valid');
        $again = $this->gate('GET', '/api/v1/gate/manifest', [], $token, ['If-None-Match' => $res->headers->get('ETag')])->assertOk();
        $this->assertNotSame($data['version'], $again->json('data.version'));
        $this->assertSame('used', collect($again->json('data.tickets'))->firstWhere('h', $h($active))['s']);
        $this->assertSame($data['salt'], $again->json('data.salt'), 'salt is stable for the day');

        // the heartbeat reports the same version
        $this->gate('POST', '/api/v1/gate/heartbeat', ['app_version' => '1.0.1', 'queue_size' => 0], $token)
            ->assertJsonPath('data.manifest_version', $again->json('data.version'));
    }

    #[Test]
    public function the_salt_differs_per_tenant_and_day(): void
    {
        $m = app(GateManifestService::class);
        $other = $this->makeTenant('other');
        $this->assertNotSame($m->salt($this->tenant, '2026-10-18'), $m->salt($other, '2026-10-18'));
        $this->assertNotSame($m->salt($this->tenant, '2026-10-18'), $m->salt($this->tenant, '2026-10-19'));
    }

    #[Test]
    public function offline_scans_sync_idempotently_and_admit_unused_tickets(): void
    {
        [$device, $token] = $this->pairDevice();
        $ticket = $this->makeTicket();
        $salt = $this->gate('GET', '/api/v1/gate/manifest', [], $token)->json('data.salt');
        $hash = hash('sha256', $salt.$ticket->qr_code);
        $scannedAt = now()->subMinutes(12)->startOfSecond();

        $batch = [
            $this->syncItem($hash, 'valid', ['scanned_at' => $scannedAt->toIso8601String(), 'idempotency_key' => 'k-1', 'source' => 'wedge']),
            $this->syncItem(hash('sha256', $salt.'GARBAGE'), 'invalid', ['idempotency_key' => 'k-2', 'source' => 'wedge']),
        ];
        $res = $this->gate('POST', '/api/v1/gate/sync', ['scans' => $batch], $token)->assertOk();
        $this->assertSame([
            ['idempotency_key' => 'k-1', 'result' => 'valid', 'conflict' => false],
            ['idempotency_key' => 'k-2', 'result' => 'invalid', 'conflict' => false],
        ], $res->json('data.results'));
        $res->assertJsonPath('data.counts.in_today', 1);

        $ticket->refresh();
        $this->assertSame(1, $ticket->check_in_count);
        $this->assertSame($scannedAt->toDateTimeString(), $ticket->used_at->toDateTimeString(), 'used_at is the device time');
        $scan = GateScan::where('idempotency_key', 'k-1')->sole();
        $this->assertTrue($scan->offline);
        $this->assertSame('wedge', $scan->source);
        $this->assertSame($scannedAt->toDateTimeString(), $scan->scanned_at->toDateTimeString());
        $this->assertSame($hash, $scan->code_hash);

        // re-sending the same batch changes nothing
        $this->gate('POST', '/api/v1/gate/sync', ['scans' => $batch], $token)->assertOk()
            ->assertJsonPath('data.results.0.result', 'valid')->assertJsonPath('data.results.1.result', 'invalid');
        $this->assertSame(2, GateScan::count());
        $this->assertSame(1, $ticket->fresh()->check_in_count);
        $this->assertSame('wedge', $device->fresh()->scanner_source);
    }

    #[Test]
    public function an_offline_admission_of_a_ticket_admitted_elsewhere_first_is_a_conflict(): void
    {
        [, $online] = $this->pairDevice(null, null, 'Online lane');
        [, $offline] = $this->pairDevice(null, null, 'Offline lane');
        $ticket = $this->makeTicket();
        $salt = app(GateManifestService::class)->salt($this->tenant, GateDay::today());
        $hash = hash('sha256', $salt.$ticket->qr_code);

        $this->scan($online, $ticket->qr_code)->assertJsonPath('data.result', 'valid');

        $res = $this->gate('POST', '/api/v1/gate/sync', ['scans' => [$this->syncItem($hash, 'valid', ['idempotency_key' => 'off-1'])]], $offline)->assertOk();
        $this->assertSame([['idempotency_key' => 'off-1', 'result' => 'already_used', 'conflict' => true]], $res->json('data.results'));
        $this->assertTrue(GateScan::where('idempotency_key', 'off-1')->sole()->conflict);
        $this->assertSame(1, $ticket->fresh()->check_in_count);

        // the same device admitting its own ticket twice offline is a duplicate, not a conflict
        $mine = $this->makeTicket();
        $mineHash = hash('sha256', $salt.$mine->qr_code);
        $res = $this->gate('POST', '/api/v1/gate/sync', ['scans' => [
            $this->syncItem($mineHash, 'valid', ['scanned_at' => now()->subMinutes(9)->toIso8601String(), 'idempotency_key' => 'm-1']),
            $this->syncItem($mineHash, 'valid', ['scanned_at' => now()->subMinutes(8)->toIso8601String(), 'idempotency_key' => 'm-2']),
        ]], $offline)->assertOk();
        $this->assertSame([false, false], array_column($res->json('data.results'), 'conflict'));
        $this->assertSame(['valid', 'already_used'], array_column($res->json('data.results'), 'result'));

        // an offline admission of a ticket the server does not know / has cancelled is a conflict too
        $cancelled = $this->makeTicket(null, ['status' => 'cancelled']);
        $res = $this->gate('POST', '/api/v1/gate/sync', ['scans' => [
            $this->syncItem(hash('sha256', $salt.'UNKNOWN'), 'valid', ['idempotency_key' => 'u-1']),
            $this->syncItem(hash('sha256', $salt.$cancelled->qr_code), 'valid', ['idempotency_key' => 'c-1']),
        ]], $offline)->assertOk();
        $this->assertSame([['idempotency_key' => 'u-1', 'result' => 'invalid', 'conflict' => true], ['idempotency_key' => 'c-1', 'result' => 'cancelled', 'conflict' => true]], $res->json('data.results'));

        // conflicts show up on the admin dashboard data
        $this->assertSame(3, GateScan::where('conflict', true)->count());
    }

    #[Test]
    public function two_offline_devices_admitting_the_same_ticket_give_one_valid_and_one_conflict(): void
    {
        [, $d1] = $this->pairDevice(null, null, 'D1');
        [, $d2] = $this->pairDevice(null, null, 'D2');
        $ticket = $this->makeTicket();
        $hash = hash('sha256', app(GateManifestService::class)->salt($this->tenant, GateDay::today()).$ticket->qr_code);

        $first = $this->gate('POST', '/api/v1/gate/sync', ['scans' => [$this->syncItem($hash, 'valid', ['scanned_at' => now()->subMinutes(20)->toIso8601String()])]], $d2)->json('data.results.0');
        $second = $this->gate('POST', '/api/v1/gate/sync', ['scans' => [$this->syncItem($hash, 'valid', ['scanned_at' => now()->subMinutes(30)->toIso8601String()])]], $d1)->json('data.results.0');

        $this->assertSame(['valid', false], [$first['result'], $first['conflict']]);
        $this->assertSame(['already_used', true], [$second['result'], $second['conflict']]);
    }

    #[Test]
    public function sync_validates_its_batch(): void
    {
        [, $token] = $this->pairDevice();
        $item = $this->syncItem(str_repeat('a', 64));

        $this->gate('POST', '/api/v1/gate/sync', ['scans' => array_fill(0, 501, $item)], $token)->assertStatus(422)->assertJsonValidationErrors('scans');
        $this->gate('POST', '/api/v1/gate/sync', ['scans' => [array_merge($item, ['code_hash' => 'xyz'])]], $token)->assertStatus(422);
        $this->gate('POST', '/api/v1/gate/sync', ['scans' => [array_merge($item, ['result_local' => 'maybe'])]], $token)->assertStatus(422);
        $this->gate('POST', '/api/v1/gate/sync', ['scans' => [$item, $item]], $token)->assertStatus(422); // duplicate keys in one batch
        $this->gate('POST', '/api/v1/gate/sync', ['scans' => []], $token)->assertStatus(422);
    }

    #[Test]
    public function stats_and_heartbeat(): void
    {
        $gate = $this->makeGate(['allow_reentry' => true]);
        [$device, $token] = $this->pairDevice($gate);
        [, $exitToken] = $this->pairDevice($this->makeGate(['direction' => 'exit']), null, 'Exit lane');
        $t1 = $this->makeTicket();
        $t2 = $this->makeTicket();

        $this->scan($token, $t1->qr_code);
        $this->scan($token, $t2->qr_code);
        $this->scan($token, $t1->qr_code);   // reentry_ok
        $this->scan($token, 'BOGUS');        // invalid
        $this->scan($exitToken, $t1->qr_code); // other gate

        $res = $this->gate('GET', '/api/v1/gate/stats', [], $token)->assertOk()
            ->assertJsonPath('data.scope', 'gate')
            ->assertJsonPath('data.in_today', 3)
            ->assertJsonPath('data.out_today', 0)
            ->assertJsonPath('data.rejects_today', 1);
        $byHour = $res->json('data.by_hour');
        $this->assertCount(1, $byHour);
        $this->assertSame((int) now('Asia/Muscat')->format('G'), $byHour[0]['hour']);
        $this->assertSame(['in' => 3, 'out' => 0, 'rejects' => 1], array_intersect_key($byHour[0], array_flip(['in', 'out', 'rejects'])));
        $this->assertCount(4, $res->json('data.recent'));
        $this->assertSame('invalid', $res->json('data.recent.0.result'));

        $this->gate('GET', '/api/v1/gate/stats', [], $exitToken)->assertJsonPath('data.out_today', 1)->assertJsonPath('data.in_today', 0);
        $this->gate('GET', '/api/v1/gate/stats?date=2020-01-01', [], $token)->assertJsonPath('data.in_today', 0)->assertJsonPath('data.date', '2020-01-01');

        $this->travel(5)->minutes();
        $hb = $this->gate('POST', '/api/v1/gate/heartbeat', ['app_version' => '1.4.0', 'battery' => 64, 'queue_size' => 7], $token)->assertOk();
        $this->assertNotNull($hb->json('data.server_time'));
        $device->refresh();
        $this->assertSame(['1.4.0', 64, 7], [$device->app_version, $device->battery, $device->queue_size]);
        $this->assertTrue($device->last_seen_at->gt(now()->subMinute()));
        $this->assertSame('online', $device->connectivity());
        $this->gate('POST', '/api/v1/gate/heartbeat', ['battery' => 140], $token)->assertStatus(422);
    }

    #[Test]
    public function the_session_describes_the_device_gate_and_todays_events(): void
    {
        $event = $this->makeEvent(null, ['title' => 'Expo Day Pass', 'title_ar' => 'تذكرة المعرض']);
        $this->makeEvent(null, ['title' => 'Next Month', 'start_time' => now()->addMonth(), 'end_time' => now()->addMonth()->addDay()]);
        $gate = $this->makeGate(['code' => 'H1', 'allow_reentry' => true]);
        [, $token] = $this->pairDevice($gate);

        $res = $this->gate('GET', '/api/v1/gate/session', [], $token, ['Accept-Language' => 'ar'])->assertOk()
            ->assertJsonPath('data.gate.code', 'H1')
            ->assertJsonPath('data.settings.allow_reentry', true)
            ->assertJsonPath('data.settings.offline_allowed', true)
            ->assertJsonPath('data.settings.beep', true)
            ->assertJsonPath('data.date', GateDay::today());
        $titles = array_column($res->json('data.events_today'), 'title');
        $this->assertContains('تذكرة المعرض', $titles);
        $this->assertNotContains('Next Month', $titles);
        $this->assertStringEndsWith('+04:00', $res->json('data.server_time'));
        $this->assertNotEmpty($res->json('data.manifest_version'));
        $this->assertNotNull($event);
    }
}
