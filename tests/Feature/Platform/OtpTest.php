<?php

namespace Tests\Feature\Platform;

use App\Http\Controllers\Api\AuthController;
use App\Jobs\SendOtpSms;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    public function otp_is_never_returned_unless_debug_flag_is_set(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '91111111'])
            ->assertOk()->assertJsonMissingPath('otp');

        config(['auth.otp.debug' => true]);
        $this->travel(3)->minutes();
        $res = $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '91111111'])->assertOk();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $res->json('otp'));

        // stored hashed, not in clear text
        $this->assertNotSame($res->json('otp'), DB::table('otp_verifications')->value('otp_code'));
    }

    #[Test]
    public function sms_text_uses_the_tenant_name(): void
    {
        $this->makeTenant('demo', ['messages' => ['otp_sms' => ['en' => 'Code for :app: :code']]]);
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '92222222'], ['X-Tenant' => 'demo', 'Accept-Language' => 'en'])->assertOk();

        Queue::assertPushed(SendOtpSms::class, fn ($job) => str_starts_with($job->message, 'Code for Demo: ') && ! str_contains($job->message, 'Sohar'));
    }

    #[Test]
    public function send_otp_is_throttled_per_phone(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '93333333'])->assertOk();
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '93333333'])
            ->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_REQUESTS');
        // other numbers are unaffected
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '94444444'])->assertOk();
    }

    #[Test]
    public function verify_enforces_max_attempts_then_logs_in_with_correct_code(): void
    {
        config(['auth.otp.test_code' => '123456']);
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '95555555'])->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => '95555555', 'otp' => '000000'])->assertStatus(401);
        }
        // even the right code is refused once attempts are exhausted
        $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => '95555555', 'otp' => '123456'])
            ->assertStatus(429)->assertJsonPath('code', 'OTP_ATTEMPTS_EXCEEDED');

        $this->travel(11)->minutes(); // send + verify limiter windows passed
        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '95555555'])->assertOk();
        $res = $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => '95555555', 'otp' => '123456'])->assertOk();
        $this->assertNotEmpty($res->json('token'));
        $this->assertNull(User::where('phone_number', '95555555')->value('email'));
        // code is single use
        $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => '95555555', 'otp' => '123456'])->assertStatus(401);
    }

    #[Test]
    public function review_number_gets_the_fixed_code_in_production_without_sms(): void
    {
        $this->app['env'] = 'production';
        config(['auth.otp.review_phone' => '96890000099', 'auth.otp.review_code' => '246810', 'auth.otp.test_code' => '123456']);

        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000099'])
            ->assertOk()->assertJsonMissingPath('otp');
        Queue::assertNotPushed(SendOtpSms::class);

        $res = $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => '96890000099', 'otp' => '246810'])->assertOk();
        $this->assertNotEmpty($res->json('token'));
    }

    #[Test]
    public function other_numbers_keep_random_codes_and_sms_when_a_review_number_is_set(): void
    {
        $this->app['env'] = 'production';
        config(['auth.otp.review_phone' => '96890000099', 'auth.otp.review_code' => '246810', 'auth.otp.test_code' => '123456']);

        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96897777777'])->assertOk();
        Queue::assertPushed(SendOtpSms::class, fn ($job) => $job->phone === '96897777777');

        // neither the review code nor the (production-ignored) test code works
        $stored = DB::table('otp_verifications')->where('phone_number', '96897777777')->value('otp_code');
        $this->assertNotSame(AuthController::hashOtp('96897777777', '246810'), $stored);
        $this->assertNotSame(AuthController::hashOtp('96897777777', '123456'), $stored);
    }

    #[Test]
    public function review_code_that_is_not_six_digits_is_ignored(): void
    {
        config(['auth.otp.review_phone' => '96890000099', 'auth.otp.review_code' => '1234']);

        $this->postJson('/api/v1/auth/send-otp', ['phone_number' => '96890000099'])->assertOk();
        Queue::assertPushed(SendOtpSms::class);
        $this->assertNotSame(
            AuthController::hashOtp('96890000099', '1234'),
            DB::table('otp_verifications')->where('phone_number', '96890000099')->value('otp_code'),
        );
    }

    #[Test]
    public function verify_otp_is_rate_limited(): void
    {
        DB::table('otp_verifications')->insert([
            'phone_number' => '96666666', 'otp_code' => AuthController::hashOtp('96666666', '654321'),
            'expires_at' => now()->addMinutes(5), 'attempts' => 0, 'is_used' => false,
        ]);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => '96666666', 'otp' => '000000']);
        }
        $this->postJson('/api/v1/auth/verify-otp', ['phone_number' => '96666666', 'otp' => '654321'])->assertStatus(429);
    }
}
