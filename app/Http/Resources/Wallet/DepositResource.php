<?php

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Deposit */
class DepositResource extends JsonResource
{
    use Money;

    public function toArray(Request $request): array
    {
        return array_merge([
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
        ], $this->money('amount', $this->amount_minor), [
            'status' => $this->status,
            'vendor_name' => $this->vendor?->localizedName(),
            'event_id' => $this->event_id,
            'customer_display_name' => $this->when($request->is('api/v1/merchant/*'), fn () => $this->user?->maskedDisplayName()),
            'created_at' => \App\Support\TenantTime::iso($this->created_at),
            'released_at' => \App\Support\TenantTime::iso($this->released_at),
        ]);
    }
}
