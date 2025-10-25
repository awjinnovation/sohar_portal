<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketValidationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $validationStatus = $this->getValidationStatus();

        return [
            'ticket_id' => (string) $this->id,
            'ticket_number' => $this->ticket_number,
            'qr_code' => $this->qr_code,
            'status' => $this->status,
            'is_valid' => $validationStatus['is_valid'],
            'event_id' => $this->event_id,
            'event_name' => $this->event?->title ?? null,
            'event_name_ar' => $this->event?->title_ar ?? null,
            'event_name_en' => $this->event?->title ?? null,
            'event_date' => $this->event?->start_time?->toIso8601String(),
            'event_location' => $this->event?->location ?? null,
            'visitor_id' => $this->user_id,
            'visitor_name' => $this->holder_name ?? $this->user?->name,
            'visitor_phone' => $this->user?->phone_number ?? null,
            'visitor_email' => $this->user?->email ?? null,
            'visit_date' => $this->booking_date?->toIso8601String(),
            'visit_time' => null, // Can be added if needed
            'ticket_type' => $this->ticket_type,
            'number_of_people' => $this->quantity ?? 1,
            'validated_at' => $this->used_at?->toIso8601String(),
            'validated_by' => $this->validated_by,
            'check_in_count' => $this->check_in_count ?? 0,
            'max_check_ins' => $this->max_check_ins ?? 1,
            'created_at' => $this->created_at?->toIso8601String(),
            'metadata' => [
                'booking_reference' => $this->ticket_number,
                'payment_status' => $this->payment?->status ?? null,
                'price' => (float) $this->price,
                'currency' => $this->currency,
            ],
        ];
    }
}
