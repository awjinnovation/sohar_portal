<?php

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PointsTransaction */
class PointsTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'points' => $this->points,
            'balance_after' => $this->balance_after,
            'title' => $this->title_key ? __($this->title_key) : $this->type,
            'reference' => $this->reference_type ? ['type' => class_basename($this->reference_type), 'id' => $this->reference_id] : null,
            'created_at' => \App\Support\TenantTime::iso($this->created_at),
            'expires_at' => \App\Support\TenantTime::iso($this->expires_at),
        ];
    }
}
