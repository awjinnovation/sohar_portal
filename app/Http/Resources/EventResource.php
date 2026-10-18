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
            'start_date' => \App\Support\TenantTime::iso($this->start_time),
            'end_date' => \App\Support\TenantTime::iso($this->end_time),
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
            'tags' => $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => LocaleHelper::getLocalizedField($tag, 'tag'),
                ];
            })->toArray(),
            'price' => $this->price ? (float) $this->price : null,
            'price_minor' => $this->unitPriceMinor(null, app(\App\Tenancy\TenantContext::class)->get()?->currencyDecimals() ?? 3),
            'deposit_minor' => (int) ($this->deposit_minor ?? 0),
            'deposit_type' => $this->deposit_type,
            'currency' => $this->currency,
            'total_tickets_per_day' => $this->total_tickets,
            'is_unlimited' => $this->total_tickets === -1,
            'daily_availability' => $this->getDailyAvailability(),
            'is_featured' => $this->is_featured ?? false,
            'is_active' => $this->is_active ?? true,
            'created_at' => \App\Support\TenantTime::iso($this->created_at),
            'updated_at' => \App\Support\TenantTime::iso($this->updated_at),
        ];
    }
}
