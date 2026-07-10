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

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $message)
    {
        $this->phone = $phone;
        $this->message = $message;
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
                Log::error('SMS not sent: iSmartSMS credentials are not configured', [
                    'phone' => $this->phone,
                ]);
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
