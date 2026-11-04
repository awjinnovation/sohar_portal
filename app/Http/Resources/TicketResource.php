<?php

namespace App\Http\Resources;

use App\Helpers\LocaleHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A purchased ticket, in the shape of the app's UserTicket model.
 *
 * @mixin \App\Models\Ticket
 */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = $this->event;

        return [
            'id' => $this->id,
            'ticket_code' => $this->ticket_number,
            'ticket_number' => $this->ticket_number,
            'qr_code' => $this->qr_code,
            'status' => $this->status,
            // Null for undated tickets (any_day / all_days): see valid_from / valid_until (docs/ticket_types_contract.md §7).
            'booking_date' => $this->booking_date?->format('Y-m-d'),
            ...$this->resource->validityApi(),
            'purchase_date' => \App\Support\TenantTime::iso($this->purchase_date),
            'used_at' => \App\Support\TenantTime::iso($this->used_at),
            'transaction_id' => $this->transaction_id,
            // docs/ticket_types_contract.md §2
            'ticket_type' => $this->resource->ticketTypeApi(),
            'admits' => $this->resource->admits(),
            // Today's entries for an all_days ticket.
            'entries_used' => $this->resource->entriesUsed(),
            'ticket' => [
                'id' => $this->event_id,
                'event_id' => $this->event_id,
                'type' => $this->ticket_type,
                'title' => $event ? LocaleHelper::getLocalizedField($event, 'title') : $this->ticket_type,
                'price' => (float) $this->price,
                'currency' => $this->currency,
                'valid_from' => \App\Support\TenantTime::isoDate($this->booking_date) ?? \App\Support\TenantTime::iso($event?->start_time),
                'valid_to' => \App\Support\TenantTime::iso($event?->end_time) ?? \App\Support\TenantTime::isoDate($this->booking_date),
                'image' => $event?->image_url,
            ],
        ];
    }
}
