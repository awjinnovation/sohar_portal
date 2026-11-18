<?php

namespace App\Jobs;

use App\Support\OpsSignals;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Its own `sms` queue with its own pool of workers (config/horizon.php), so a
 * login code never waits behind payment jobs. Encrypted on the queue: the
 * message holds the code, and Horizon keeps job payloads for its dashboard.
 */
class SendOtpSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public $phone;
    public $message;
    /** otp | box_office — box office links are logged in full outside production. */
    public $purpose;
    /** Unix time it was queued: the operations room shows how long SMS wait (null for jobs queued before this field). */
    public $queuedAt;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $message, string $purpose = 'otp')
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->purpose = $purpose;
        $this->queuedAt = time();
        $this->onQueue('sms');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $userId = config('services.ismartsms.user_id');
            $password = config('services.ismartsms.password');

            if (!$userId || !$password) {
                // Local / unconfigured: the text of non-OTP messages (e.g. a
                // box office payment link) is logged so it can be opened.
                Log::error('SMS not sent: iSmartSMS credentials are not configured', array_filter([
                    'phone' => $this->phone,
                    'purpose' => $this->purpose ?? 'otp',
                    'message' => ($this->purpose ?? 'otp') !== 'otp' && ! app()->environment('production') ? $this->message : null,
                ]));
                OpsSignals::count('sms.skipped');
                return;
            }

            $response = Http::get(config('services.ismartsms.url'), [
                'UserId' => $userId,
                'Password' => $password,
                'Language' => 0,
                'Lang' => 0,
                'FLashSMS' => 'N',
                'PushDateTime' => date('m/d/Y h:i:s'),
                'MobileNo' => $this->phone,
                'Message' => $this->message,
            ]);

            if ($response->successful()) {
                $this->recordSent();
                Log::info('SMS sent successfully', [
                    'phone' => $this->phone,
                    'response' => $response->body()
                ]);
            } else {
                OpsSignals::count('sms.failed');
                Log::error('SMS sending failed', [
                    'phone' => $this->phone,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SMS sending exception', [
                'phone' => $this->phone,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /** Every attempt failed with an exception (an error response is counted in handle()). */
    public function failed(?\Throwable $e): void
    {
        OpsSignals::count('sms.failed');
    }

    private function recordSent(): void
    {
        OpsSignals::count('sms.sent');
        OpsSignals::put('sms:last_sent_at', time());
        if ($this->queuedAt) {
            OpsSignals::put('sms:last_wait', max(0, time() - (int) $this->queuedAt));
        }
    }
}
