<?php

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WalletTopup */
class TopupResource extends JsonResource
{
    use Money;

    public ?string $paymentUrl = null;

    public function toArray(Request $request): array
    {
        return array_merge([
            'topup_id' => $this->id,
            'transaction_id' => $this->payment?->transaction_id,
            'status' => $this->status,
        ], $this->money('amount', $this->amount_minor), $this->money('bonus', (int) $this->bonus_minor),
            $this->paymentUrl ? ['payment_url' => $this->paymentUrl] : [], [
                'created_at' => \App\Support\TenantTime::iso($this->created_at),
                'completed_at' => \App\Support\TenantTime::iso($this->completed_at),
            ]);
    }
}
