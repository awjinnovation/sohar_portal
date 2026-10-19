<?php

namespace App\Http\Resources\Discover;

use App\Support\Localized;
use App\Support\MediaPresenter;
use App\Support\TenantTime;
use Illuminate\Http\Request;

/**
 * Collection item (contract §3 GET /content/collections/{key}/items);
 * `detail()` = GET /content/items/{id} (body html, phone, email, place,
 * location, gallery).
 */
class ContentItemResource extends LocalizedResource
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
        $label = array_filter((array) $this->link_label) ? $this->link_label : self::defaultLinkLabel();

        $data = array_merge(
            ['id' => $this->id, 'slug' => $this->slug, 'collection_key' => $this->collection?->key],
            $this->loc('title'),
            $this->loc('subtitle'),
            [
                'icon' => $this->icon,
                'image' => MediaPresenter::image($this->getFirstMedia('image')),
                'link_url' => $this->link_url,
                'link_label' => $this->link_url ? Localized::pair($label) : Localized::pair(null),
                'link_label_text' => $this->link_url ? Localized::text($label) : null,
                'featured' => (bool) $this->featured,
                'tags' => array_values(array_filter((array) $this->tags, 'is_string')),
            ],
        );

        if (! $this->detail) {
            return $data;
        }

        $place = $this->place && $this->place->isPublished() ? $this->place : null;
        $lat = $place ? (float) $place->latitude : ($this->latitude !== null ? (float) $this->latitude : null);
        $lng = $place ? (float) $place->longitude : ($this->longitude !== null ? (float) $this->longitude : null);

        return array_merge($data, $this->html('body'), [
            'phone' => $this->phone,
            'email' => $this->email,
            'place' => $place ? array_merge(
                ['id' => $place->id, 'slug' => $place->slug],
                (new PlaceResource($place))->loc('name'),
                [
                    'icon' => $place->displayIcon(),
                    'color' => $place->displayColor(),
                    'latitude' => (float) $place->latitude,
                    'longitude' => (float) $place->longitude,
                    'cover' => MediaPresenter::image($place->getFirstMedia('cover')),
                ],
            ) : null,
            'latitude' => $lat,
            'longitude' => $lng,
            'gallery' => $this->getMedia('gallery')->map(fn ($m) => MediaPresenter::image($m))->values()->all(),
            'collection' => $this->collection ? array_merge(
                ['id' => $this->collection->id, 'key' => $this->collection->key],
                (new ContentCollectionResource($this->collection))->loc('name'),
                ['layout' => $this->collection->layout],
            ) : null,
            'updated_at' => TenantTime::iso($this->updated_at),
        ]);
    }
}
