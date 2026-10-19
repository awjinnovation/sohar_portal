<?php

namespace App\Http\Resources\Discover;

use App\Support\MediaPresenter;
use App\Support\TenantTime;
use Illuminate\Http\Request;

/** `[{id, slug, title, title_text, icon, cover, show_in_more}]`; `detail()` adds body html. */
class ContentPageResource extends LocalizedResource
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
        $data = array_merge(
            ['id' => $this->id, 'slug' => $this->slug],
            $this->loc('title'),
            [
                'icon' => $this->icon,
                'cover' => MediaPresenter::image($this->getFirstMedia('cover')),
                'show_in_more' => (bool) $this->show_in_more,
                'sort' => (int) $this->sort,
            ],
        );

        return $this->detail
            ? array_merge($data, $this->html('body'), ['updated_at' => TenantTime::iso($this->updated_at)])
            : $data;
    }
}
