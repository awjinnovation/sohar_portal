<?php

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RefundRequest */
class RefundRequestResource extends JsonResource
{
    use Money;

    public function toArray(Request $request): array
    {
        return array_merge(
            ['id' => $this->id],
            $this->money('amount', $this->amount_minor),
            $this->money('fee', $this->fee_minor),
            $this->money('payout', max(0, $this->amount_minor - $this->fee_minor)),
            [
                'method' => $this->method,
                'status' => $this->status,
                'reason' => $this->reason,
                'bank_name' => $this->bank_name,
                'bank_iban_last4' => $this->bank_iban ? substr($this->bank_iban, -4) : null,
                'admin_note' => $this->admin_note,
                'created_at' => \App\Support\TenantTime::iso($this->created_at),
                'decided_at' => \App\Support\TenantTime::iso($this->decided_at),
                'paid_at' => \App\Support\TenantTime::iso($this->paid_at),
            ]
        );
    }
}
