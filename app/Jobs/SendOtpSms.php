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
            $url = "https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx";
            $url .= "?UserId=hus_sm123";
            $url .= "&Password=L2ilcg40";
            $url .= "&Language=0";
            $url .= "&Lang=0";
            $url .= "&FLashSMS=N";
            $url .= "&PushDateTime=" . date('m/d/Y h:i:s', strtotime(now()));
            $url .= "&MobileNo=" . $this->phone;
            $url .= "&Message=" . urlencode($this->message);

            $response = Http::get($url);

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
