<?php

namespace Tests\Feature\Platform;

use App\Jobs\SendOtpSms;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MSR-347: login SMS have their own queue and pool of workers (Horizon), and
 * the Horizon dashboard is for platform super-admins only.
 */
class QueuePoolsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_sms_go_to_their_own_queue_encrypted(): void
    {
        Queue::fake();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '91111111'])->assertOk();

        Queue::assertPushedOn('sms', SendOtpSms::class);
        $this->assertInstanceOf(ShouldBeEncrypted::class, new SendOtpSms('96891111111', 'x'));
    }

    #[Test]
    public function every_queue_has_a_pool_and_sms_have_twenty_workers(): void
    {
        $pools = collect(config('horizon.defaults'));
        $queues = $pools->flatMap(fn ($p) => $p['queue'])->all();

        $this->assertContains('sms', $queues);
        // Where the payment jobs go (ProcessPaymentWebhook, ReconcilePayment, …).
        $this->assertContains(config('payments.queue', 'payments'), $queues);
        $this->assertContains('default', $queues);
        $this->assertSame(20, $pools['sms']['maxProcesses']);
        $this->assertSame(20, $pools['sms']['minProcesses']);
        $this->assertFalse($pools['sms']['balance']);
        // One queue per pool: a worker blocking on Redis (block_for) for one
        // queue would otherwise delay the next queue in its list.
        $pools->each(fn ($p) => $this->assertCount(1, $p['queue']));
        $this->assertSame(5, config('queue.connections.redis.block_for'));
    }

    #[Test]
    public function only_platform_super_admins_open_the_horizon_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->get('/horizon')->assertForbidden();

        $tenantAdmin = User::factory()->create();
        $tenantAdmin->assignRole('admin');
        $this->actingAs($tenantAdmin)->get('/horizon')->assertForbidden();

        $super = User::factory()->create();
        $super->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($super)->get('/horizon')->assertOk();
    }
}
