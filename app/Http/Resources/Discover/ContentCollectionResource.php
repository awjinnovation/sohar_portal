<?php

namespace App\Http\Resources\Discover;

use Illuminate\Http\Request;

/** `[{id, key, name, name_text, description, description_text, icon, layout, items_count, show_in_discover}]` */
class ContentCollectionResource extends LocalizedResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            ['id' => $this->id, 'key' => $this->key],
            $this->loc('name'),
            $this->loc('description'),
            [
                'icon' => $this->icon,
                'layout' => $this->layout,
                'items_count' => (int) ($this->published_items_count ?? 0),
                'show_in_discover' => (bool) $this->show_in_discover,
                'sort' => (int) $this->sort,
            ],
        );
    }
}
