<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreatePlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generate_creates_a_super_admin_with_a_random_password(): void
    {
        $this->artisan('platform:create-admin', ['email' => 'Ops@AWJ.om', '--generate' => true])
            ->expectsOutputToContain('Created platform super-admin ops@awj.om')
            ->expectsOutputToContain('Password (shown once')
            ->assertSuccessful();

        $user = User::where('email', 'ops@awj.om')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertSame('Platform Admin', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse(Hash::check('password123', $user->password));
    }

    #[Test]
    public function prompts_twice_rejects_weak_passwords_and_promotes_an_existing_user(): void
    {
        $existing = User::factory()->create(['email' => 'lead@awj.om', 'name' => 'Lead', 'password' => 'old-password-1']);

        $this->artisan('platform:create-admin', ['email' => 'lead@awj.om'])
            ->expectsQuestion('Password (min 12 characters)', 'short')
            ->expectsOutputToContain('Too short')
            ->expectsQuestion('Password (min 12 characters)', 'Correct-Horse-Battery-9')
            ->expectsQuestion('Repeat the password', 'Correct-Horse-Battery-9')
            ->expectsOutputToContain('Updated platform super-admin lead@awj.om')
            ->assertSuccessful();

        $existing->refresh();
        $this->assertTrue($existing->isSuperAdmin());
        $this->assertSame('Lead', $existing->name);
        $this->assertTrue(Hash::check('Correct-Horse-Battery-9', $existing->password));
    }

    #[Test]
    public function mismatched_or_missing_passwords_and_bad_emails_fail(): void
    {
        $this->artisan('platform:create-admin', ['email' => 'not-an-email', '--generate' => true])->assertFailed();

        $this->artisan('platform:create-admin', ['email' => 'x@awj.om'])
            ->expectsQuestion('Password (min 12 characters)', 'Correct-Horse-Battery-9')
            ->expectsQuestion('Repeat the password', 'something-else-entirely')
            ->expectsQuestion('Password (min 12 characters)', 'password123password123')
            ->expectsQuestion('Repeat the password', 'nope')
            ->expectsQuestion('Password (min 12 characters)', 'aaaaaaaaaaaaaaaa')
            ->expectsOutputToContain('Too easy to guess')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'x@awj.om']);
    }

    #[Test]
    public function lock_seeded_replaces_the_default_password_only(): void
    {
        $seeded = User::factory()->create(['email' => 'admin@demo-events.example', 'password' => 'password123']);
        $custom = User::factory()->create(['email' => 'kept@awj.om', 'password' => 'a-real-password-42']);
        $visitor = User::factory()->create(['email' => null, 'phone_number' => '96891234567', 'password' => 'password123']);

        $this->artisan('platform:create-admin', ['email' => 'ops@awj.om', '--generate' => true, '--lock-seeded' => true])
            ->expectsOutputToContain('locked admin@demo-events.example')
            ->expectsOutputToContain('Replaced the seeded default password on 1 account(s).')
            ->assertSuccessful();

        $this->assertFalse(Hash::check('password123', $seeded->refresh()->password));
        $this->assertTrue(Hash::check('a-real-password-42', $custom->refresh()->password));
        $this->assertTrue(Hash::check('password123', $visitor->refresh()->password)); // OTP-only account, skipped
    }
}
