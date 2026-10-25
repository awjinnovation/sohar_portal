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
            'booking_date' => $this->booking_date?->format('Y-m-d'),
            'purchase_date' => \App\Support\TenantTime::iso($this->purchase_date),
            'used_at' => \App\Support\TenantTime::iso($this->used_at),
            'transaction_id' => $this->transaction_id,
            // docs/ticket_types_contract.md §2
            'ticket_type' => $this->resource->ticketTypeApi(),
            'admits' => $this->resource->admits(),
            'entries_used' => (int) $this->check_in_count,
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
