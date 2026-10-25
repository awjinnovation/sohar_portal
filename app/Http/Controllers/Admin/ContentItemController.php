<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DiscoverForm;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\Place;
use Illuminate\Http\Request;

/**
 * Admin: items of one collection (/admin/content/collections/{collection}/items),
 * permission `manage content`. An item is located by a linked place or by its
 * own latitude/longitude (same map picker as places).
 */
class ContentItemController extends Controller
{
    use DiscoverForm;

    private function ensureOwned(ContentCollection $collection, ?ContentItem $item = null): void
    {
        abort_if($item && $item->collection_id !== $collection->id, 404);
    }

    public function index(Request $request, ContentCollection $collection)
    {
        $query = $collection->items()->with('media');
        if ($request->filled('q')) {
            $query->searchLocalized((string) $request->q, ['title', 'subtitle', 'tags']);
        }
        if (in_array($request->status, ContentItem::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        $items = $query->orderBy('sort')->orderBy('id')->paginate(50);
        $sortable = ! $request->filled('q') && ! $request->filled('status');

        return view('admin.content-items.index', compact('collection', 'items', 'sortable'));
    }

    public function create(ContentCollection $collection)
    {
        return $this->form($collection, new ContentItem(['status' => 'draft']));
    }

    public function edit(ContentCollection $collection, ContentItem $item)
    {
        $this->ensureOwned($collection, $item);

        return $this->form($collection, $item->load('media'));
    }

    private function form(ContentCollection $collection, ContentItem $item)
    {
        $places = Place::query()->with('category')->orderBy('sort')->get();
        $others = $places->map(fn (Place $p) => [(float) $p->latitude, (float) $p->longitude, $p->t('name'), $p->displayColor()])->all();
        $tenant = $this->currentTenant();
        $center = [(float) $tenant?->setting('map.center.lat', 24.35), (float) $tenant?->setting('map.center.lng', 56.72)];

        return view('admin.content-items.form', compact('collection', 'item', 'places', 'others', 'center'));
    }

    private function rules(): array
    {
        return array_merge(
            $this->localizedRules('title', 150, true),
            $this->localizedRules('subtitle', 200),
            $this->localizedRules('body', 20000),
            $this->localizedRules('link_label', 60),
            $this->iconRules(),
            $this->galleryRules(),
            [
                'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'link_url' => ['nullable', 'url:http,https', 'max:500'],
                'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() \-]{3,40}$/'],
                'email' => ['nullable', 'email', 'max:190'],
                'location_mode' => ['nullable', 'in:none,place,own'],
                'place_id' => ['nullable', 'required_if:location_mode,place', 'integer', $this->tenantExists('places')],
                'latitude' => ['nullable', 'required_if:location_mode,own', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'required_if:location_mode,own', 'numeric', 'between:-180,180'],
                'tags' => ['nullable', 'string', 'max:300'],
                'featured' => ['nullable', 'boolean'],
                'sort' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'status' => ['required', 'in:draft,published'],
                'image' => ['nullable', 'file', ...explode('|', self::IMAGE_RULE)],
                'remove_image' => ['nullable', 'boolean'],
            ],
        );
    }

    private function fill(ContentCollection $collection, ContentItem $item, Request $request): ContentItem
    {
        $title = $this->localized($request, 'title');
        $mode = $request->input('location_mode', 'none');
        $tags = collect(preg_split('/[,\x{060C}]/u', (string) $request->input('tags', '')))
            ->map(fn ($t) => trim($t))->filter()->unique()->take(12)->values()->all();

        $item->fill([
            'collection_id' => $collection->id,
            'title' => $title,
            'subtitle' => $this->localized($request, 'subtitle'),
            'body' => $this->localizedHtml($request, 'body'),
            'icon' => $request->input('icon') ?: null,
            'link_url' => $request->input('link_url') ?: null,
            'link_label' => $this->localized($request, 'link_label'),
            'phone' => $request->input('phone') ?: null,
            'email' => $request->input('email') ?: null,
            'place_id' => $mode === 'place' ? (int) $request->input('place_id') : null,
            'latitude' => $mode === 'own' ? round((float) $request->input('latitude'), 7) : null,
            'longitude' => $mode === 'own' ? round((float) $request->input('longitude'), 7) : null,
            'tags' => $tags ?: null,
            'featured' => $request->boolean('featured'),
            'status' => $request->input('status'),
        ]);
        if ($request->filled('sort')) {
            $item->sort = (int) $request->input('sort');
        } elseif (! $item->exists) {
            $item->sort = (int) $collection->items()->max('sort') + 1;
        }
        if ($item->status === 'draft') {
            $item->published_at = null;
        }
        $item->slug = ContentItem::uniqueSlug($request->input('slug') ?: ($item->exists ? $item->slug : null), $title ?? [],
            ['collection_id' => $collection->id], $item->id, 'item');

        return $item;
    }

    public function store(Request $request, ContentCollection $collection)
    {
        $request->validate($this->rules());
        $item = $this->fill($collection, new ContentItem, $request);
        $item->save();
        $this->syncMedia($request, $item, 'image');

        return redirect()->route('admin.content-items.edit', [$collection, $item])->with('success', 'أُضيف العنصر');
    }

    public function update(Request $request, ContentCollection $collection, ContentItem $item)
    {
        $this->ensureOwned($collection, $item);
        $request->validate($this->rules());
        $this->fill($collection, $item, $request)->save();
        $this->syncMedia($request, $item, 'image');

        return redirect()->route('admin.content-items.edit', [$collection, $item])->with('success', 'حُفظ العنصر');
    }

    public function destroy(ContentCollection $collection, ContentItem $item)
    {
        $this->ensureOwned($collection, $item);
        $item->delete();

        return redirect()->route('admin.content-items.index', $collection)->with('success', 'حُذف العنصر');
    }

    public function reorder(Request $request, ContentCollection $collection)
    {
        return $this->reorderRows($request, ContentItem::class, ['collection_id' => $collection->id]);
    }
}
