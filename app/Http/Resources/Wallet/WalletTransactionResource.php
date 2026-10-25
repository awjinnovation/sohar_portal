<?php

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WalletTransaction */
class WalletTransactionResource extends JsonResource
{
    use Money;

    public function toArray(Request $request): array
    {
        $meta = $this->meta ?? [];
        $ar = app()->getLocale() === 'ar';
        $params = [
            'event' => ($ar ? ($meta['event_title_ar'] ?? null) : null) ?? $meta['event_title'] ?? '',
            'vendor' => $this->localized($meta['vendor_name'] ?? null) ?? ($this->vendor ? $this->vendor->localizedName() : ''),
            'label' => $meta['label'] ?? '',
            'id' => $meta['refund_request_id'] ?? '',
            'points' => $meta['points'] ?? '',
            'reason' => $meta['reason'] ?? '',
        ];

        return array_merge([
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'direction' => $this->direction,
        ], $this->money('amount', $this->amount_minor), $this->money('balance_after', $this->balance_after_minor), [
            'available_after_minor' => max(0, $this->balance_after_minor - $this->held_after_minor),
            'bucket' => $meta['bucket'] ?? 'cash',
            'title' => $this->title_key ? __($this->title_key, $params) : $this->type,
            'description' => $this->description_key ? trim(__($this->description_key, $params)) : null,
            'reference' => $this->reference_type ? ['type' => class_basename($this->reference_type), 'id' => $this->reference_id] : null,
            'status' => $this->status,
            // Promo credits only: when the credit stops being spendable (null = never).
            'expires_at' => isset($meta['expires_at']) ? \App\Support\TenantTime::iso(\Carbon\Carbon::parse($meta['expires_at'])) : null,
            'created_at' => \App\Support\TenantTime::iso($this->created_at),
            'meta' => $meta,
        ]);
    }
}
