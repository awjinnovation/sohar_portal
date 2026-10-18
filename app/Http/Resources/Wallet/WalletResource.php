<?php

namespace App\Http\Resources\Wallet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET /wallet. Pass ['wallet' => Wallet, 'points' => array|null].
 */
class WalletResource extends JsonResource
{
    use Money;

    public function toArray(Request $request): array
    {
        $w = $this->resource['wallet'];
        $points = $this->resource['points'] ?? null;

        return array_merge(
            ['id' => $w->id, 'status' => $w->status, 'currency' => $w->currency],
            $this->money('balance', $w->balance_minor),
            $this->money('available', $w->availableMinor()),
            $this->money('held_deposits', $w->held_minor),
            $this->money('cash', $w->cash_minor),
            $this->money('promo', $w->promo_minor),
            $this->money('refundable', $w->refundableMinor()),
            ['points' => $points]
        );
    }
}
