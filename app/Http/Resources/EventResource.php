<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'images' => $this->images ?? [],
            'start_date' => $this->start_time?->toIso8601String(),
            'end_date' => $this->end_time?->toIso8601String(),
            'location' => $this->location,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'category' => $this->category?->name ?? '',
            'tags' => $this->tags->pluck('name')->toArray() ?? [],
            'price' => $this->price ? (float) $this->price : null,
            'currency' => $this->currency,
            'organizer_name' => $this->organizer_name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
