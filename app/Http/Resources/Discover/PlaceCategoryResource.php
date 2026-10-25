<?php

namespace App\Http\Resources\Discover;

use Illuminate\Http\Request;

/** `[{id, key, name, name_text, icon, color, places_count}]` */
class PlaceCategoryResource extends LocalizedResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            ['id' => $this->id, 'key' => $this->key],
            $this->loc('name'),
            [
                'icon' => $this->icon,
                'color' => $this->color,
                'places_count' => (int) ($this->published_places_count ?? 0),
            ],
        );
    }
}
