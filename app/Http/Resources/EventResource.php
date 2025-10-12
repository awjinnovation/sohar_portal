<?php

namespace App\Http\Resources;

use App\Helpers\LocaleHelper;
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
        $locale = LocaleHelper::getLocale();

        return [
            'id' => (string) $this->id,
            'title' => LocaleHelper::getLocalizedField($this, 'title'),
            'description' => LocaleHelper::getLocalizedField($this, 'description'),
            'location' => LocaleHelper::getLocalizedField($this, 'location'),
            'organizer_name' => LocaleHelper::getLocalizedField($this, 'organizer_name'),
            'image_url' => $this->image_url,
            'images' => $this->images ?? [],
            'start_date' => $this->start_time?->toIso8601String(),
            'end_date' => $this->end_time?->toIso8601String(),
            'map_location' => $this->mapLocation ? [
                'id' => $this->mapLocation->id,
                'name' => LocaleHelper::getLocalizedField($this->mapLocation, 'name'),
                'description' => LocaleHelper::getLocalizedField($this->mapLocation, 'description'),
                'type' => $this->mapLocation->type,
                'type_translation' => $this->mapLocation->getTypeTranslation($locale),
                'latitude' => $this->mapLocation->latitude ? (float) $this->mapLocation->latitude : null,
                'longitude' => $this->mapLocation->longitude ? (float) $this->mapLocation->longitude : null,
                'icon' => $this->mapLocation->icon,
                'color' => $this->mapLocation->color,
            ] : null,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => LocaleHelper::getLocalizedField($this->category, 'name'),
                'description' => LocaleHelper::getLocalizedField($this->category, 'description'),
            ] : null,
            'tags' => $this->tags->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => LocaleHelper::getLocalizedField($tag, 'name'),
                ];
            })->toArray(),
            'price' => $this->price ? (float) $this->price : null,
            'currency' => $this->currency,
            'available_tickets' => $this->available_tickets,
            'total_tickets' => $this->total_tickets,
            'tickets_remaining' => $this->available_tickets,
            'is_sold_out' => $this->total_tickets > 0 && $this->available_tickets <= 0,
            'is_featured' => $this->is_featured ?? false,
            'is_active' => $this->is_active ?? true,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
