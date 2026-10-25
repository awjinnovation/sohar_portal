<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOtpSms implements ShouldQueue
{
    use Queueable;

    public $phone;
    public $message;
    /** otp | box_office — box office links are logged in full outside production. */
    public $purpose;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $message, string $purpose = 'otp')
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->purpose = $purpose;
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
                Log::info('SMS sent successfully', [
                    'phone' => $this->phone,
                    'response' => $response->body()
                ]);
            } else {
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
}
