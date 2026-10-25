<?php

namespace App\Http\Resources\Discover;

use App\Support\MediaPresenter;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Place list item (contract §3 GET /places); `detail()` adds the fields of
 * GET /places/{id}. `distance_m` only when the list is sorted by `near`.
 */
class PlaceResource extends LocalizedResource
{
    public bool $detail = false;

    public static function detail($resource): static
    {
        $r = new static($resource);
        $r->detail = true;

        return $r;
    }

    public function toArray(Request $request): array
    {
        $tenant = app(TenantContext::class)->get();
        $category = $this->category;
        $fee = $this->entry_fee_minor;

        $data = array_merge(
            ['id' => $this->id, 'slug' => $this->slug],
            $this->loc('name'),
            $this->loc('summary'),
            [
                'category' => $category ? array_merge(
                    ['id' => $category->id, 'key' => $category->key],
                    (new PlaceCategoryResource($category))->loc('name'),
                    ['icon' => $category->icon, 'color' => $category->color],
                ) : null,
                'icon' => $this->displayIcon(),
                'color' => $this->displayColor(),
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'cover' => MediaPresenter::image($this->getFirstMedia('cover')),
            ],
            $this->loc('wilayat'),
            [
                'featured' => (bool) $this->featured,
                'entry_fee_minor' => $fee,
                'entry_fee' => $fee === null ? null : $tenant?->formatMoney($fee),
                'currency' => $tenant?->currency(),
            ],
        );

        if (isset($this->resource->distance_m)) {
            $data['distance_m'] = (int) round($this->resource->distance_m);
        }

        if (! $this->detail) {
            return $data;
        }

        $label = $this->link_label;
        if (! array_filter((array) $label)) {
            $label = self::defaultLinkLabel();
        }

        return array_merge($data,
            $this->html('description'),
            $this->loc('address'),
            $this->loc('opening_hours'),
            [
                'phone' => $this->phone,
                'link_url' => $this->link_url,
            ],
            $this->link_url ? ['link_label' => \App\Support\Localized::pair($label), 'link_label_text' => \App\Support\Localized::text($label)]
                : ['link_label' => \App\Support\Localized::pair(null), 'link_label_text' => null],
            [
                'gallery' => $this->getMedia('gallery')->map(fn ($m) => MediaPresenter::image($m))->values()->all(),
                'events' => $this->upcomingEvents($tenant),
                'share_url' => url('/discover/places/'.rawurlencode($this->slug)).'?'.http_build_query(['tenant' => $tenant?->slug, 'lang' => app()->getLocale()]),
                'published_at' => TenantTime::iso($this->published_at),
                'updated_at' => TenantTime::iso($this->updated_at),
            ],
        );
    }

    private function upcomingEvents($tenant): array
    {
        if (! $tenant?->moduleEnabled('events')) {
            return [];
        }
        $decimals = $tenant->currencyDecimals();

        return $this->events()->where('events.is_active', true)
            ->where('events.end_time', '>=', TenantTime::localNow($tenant))
            ->orderBy('events.start_time')->limit(10)->get()
            ->map(function ($e) use ($tenant, $decimals) {
                $price = $e->unitPriceMinor(null, $decimals);

                return [
                    'id' => $e->id,
                    'title' => ['en' => $e->title ?: null, 'ar' => $e->title_ar ?: null],
                    'title_text' => app()->getLocale() === 'ar' ? ($e->title_ar ?: $e->title) : ($e->title ?: $e->title_ar),
                    'start_date' => TenantTime::iso($e->start_time),
                    'end_date' => TenantTime::iso($e->end_time),
                    'image_url' => $e->image_url,
                    'price_minor' => $price,
                    'price' => $tenant->formatMoney($price),
                    'is_free' => $price === 0,
                ];
            })->all();
    }
}
