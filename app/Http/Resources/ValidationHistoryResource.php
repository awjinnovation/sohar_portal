<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidationHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => (string) $this->id,
            'ticket_number' => $this->ticket_number,
            'event_name' => $this->event?->title ?? null,
            'visitor_name' => $this->holder_name ?? $this->user?->name,
            'status' => $this->status,
            'validated_at' => $this->used_at?->toIso8601String(),
            'validated_by' => $this->validated_by,
            'location' => $this->validation_location,
        ];
    }
}
