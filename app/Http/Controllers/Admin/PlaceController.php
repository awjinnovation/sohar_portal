<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DiscoverForm;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Place;
use App\Models\PlaceCategory;
use App\Support\MediaPresenter;
use Illuminate\Http\Request;

/**
 * Admin: places to visit (docs/places_content_contract.md §4). Permission
 * `manage places` (route middleware). Everything runs inside the current
 * tenant (global scope + tenant-scoped validation rules).
 */
class PlaceController extends Controller
{
    use DiscoverForm;

    public function index(Request $request)
    {
        $query = Place::query()->with(['category', 'media']);
        if ($request->filled('q')) {
            $query->searchLocalized((string) $request->q, ['name', 'summary', 'wilayat', 'slug']);
        }
        if ($request->filled('category')) {
            $query->where('category_id', (int) $request->category);
        }
        if (in_array($request->status, Place::STATUSES, true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('featured')) {
            $query->where('featured', $request->boolean('featured'));
        }

        $places = $query->orderBy('sort')->orderBy('id')->paginate(50);
        $categories = PlaceCategory::orderBy('sort')->orderBy('id')->get();
        $stats = [
            'total' => Place::count(),
            'published' => Place::where('status', 'published')->count(),
            'draft' => Place::where('status', 'draft')->count(),
            'featured' => Place::where('featured', true)->count(),
        ];
        $sortable = ! $request->hasAny(['q', 'category', 'status', 'featured']) || collect($request->only(['q', 'category', 'status', 'featured']))->filter(fn ($v) => $v !== null && $v !== '')->isEmpty();

        return view('admin.places.index', compact('places', 'categories', 'stats', 'sortable'));
    }

    public function map()
    {
        $places = Place::query()->with(['category', 'media'])->orderBy('sort')->get();
        $categories = PlaceCategory::orderBy('sort')->orderBy('id')->withCount('places')->get();

        return view('admin.places.map', compact('places', 'categories'));
    }

    public function create()
    {
        return $this->form(new Place(['status' => 'draft', 'latitude' => null, 'longitude' => null]));
    }

    public function edit(Place $place)
    {
        return $this->form($place->load(['category', 'media', 'events']));
    }

    private function form(Place $place)
    {
        $categories = PlaceCategory::orderBy('sort')->orderBy('id')->get();
        $events = Event::query()->orderByDesc('start_time')->limit(300)->get(['id', 'title', 'title_ar', 'start_time']);
        $others = Place::query()->with('category')->when($place->exists, fn ($q) => $q->where('id', '!=', $place->id))
            ->get()->map(fn (Place $p) => [(float) $p->latitude, (float) $p->longitude, $p->t('name'), $p->displayColor()])->all();
        $tenant = $this->currentTenant();
        $center = [(float) $tenant?->setting('map.center.lat', 24.35), (float) $tenant?->setting('map.center.lng', 56.72)];

        return view('admin.places.form', compact('place', 'categories', 'events', 'others', 'center'));
    }

    private function rules(?Place $place = null): array
    {
        return array_merge(
            $this->localizedRules('name', 150, true),
            $this->localizedRules('summary', 200),
            $this->localizedRules('description', 20000),
            $this->localizedRules('address', 255),
            $this->localizedRules('wilayat', 80),
            $this->localizedRules('link_label', 60),
            $this->localizedRules('opening_hours', 500),
            $this->iconRules(),
            $this->galleryRules(),
            [
                'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'category_id' => ['nullable', 'integer', $this->tenantExists('place_categories')],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'link_url' => ['nullable', 'url:http,https', 'max:500'],
                'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() \-]{3,40}$/'],
                'entry_fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'featured' => ['nullable', 'boolean'],
                'sort' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'status' => ['required', 'in:draft,published'],
                'events' => ['nullable', 'array'],
                'events.*' => ['integer', $this->tenantExists('events')],
                'cover' => ['nullable', 'file', ...explode('|', self::IMAGE_RULE)],
                'remove_cover' => ['nullable', 'boolean'],
            ],
        );
    }

    private function fill(Place $place, Request $request): Place
    {
        $tenant = $this->currentTenant();
        $name = $this->localized($request, 'name');
        $fee = $request->input('entry_fee');

        $place->fill([
            'category_id' => $request->input('category_id') ?: null,
            'name' => $name,
            'summary' => $this->localized($request, 'summary'),
            'description' => $this->localizedHtml($request, 'description'),
            'address' => $this->localized($request, 'address'),
            'wilayat' => $this->localized($request, 'wilayat'),
            'link_url' => $request->input('link_url') ?: null,
            'link_label' => $this->localized($request, 'link_label'),
            'phone' => $request->input('phone') ?: null,
            'opening_hours' => $this->localized($request, 'opening_hours'),
            'entry_fee_minor' => $fee === null || $fee === '' ? null : $tenant->toMinor((string) $fee),
            'icon' => $request->input('icon') ?: null,
            'latitude' => round((float) $request->input('latitude'), 7),
            'longitude' => round((float) $request->input('longitude'), 7),
            'featured' => $request->boolean('featured'),
            'status' => $request->input('status'),
        ]);
        if ($request->filled('sort')) {
            $place->sort = (int) $request->input('sort');
        } elseif (! $place->exists) {
            $place->sort = (int) Place::max('sort') + 1;
        }
        if ($place->status === 'draft') {
            $place->published_at = null;
        }
        $place->slug = Place::uniqueSlug($request->input('slug') ?: ($place->exists ? $place->slug : null), $name ?? [],
            ['tenant_id' => $this->currentTenantId()], $place->id, 'place');

        return $place;
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());
        $place = $this->fill(new Place, $request);
        $place->save();
        $place->events()->sync((array) $request->input('events', []));
        $this->syncMedia($request, $place, 'cover');

        return redirect()->route('admin.places.edit', $place)->with('success', 'أُضيف المكان');
    }

    public function update(Request $request, Place $place)
    {
        $request->validate($this->rules($place));
        $this->fill($place, $request)->save();
        $place->events()->sync((array) $request->input('events', []));
        $this->syncMedia($request, $place, 'cover');

        return redirect()->route('admin.places.edit', $place)->with('success', 'حُفظ المكان');
    }

    public function destroy(Place $place)
    {
        $place->delete();

        return redirect()->route('admin.places.index')->with('success', 'حُذف المكان');
    }

    /** POST /admin/places/{place}/featured — toggle (form or fetch). */
    public function featured(Request $request, Place $place)
    {
        $place->forceFill(['featured' => ! $place->featured])->save();
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['featured' => $place->featured]]);
        }

        return back()->with('success', $place->featured ? 'أصبح المكان مميزًا' : 'أُلغي تمييز المكان');
    }

    /** POST /admin/places/reorder {ids: [...]} — drag-to-sort. */
    public function reorder(Request $request)
    {
        return $this->reorderRows($request, Place::class);
    }

    /** Data for the admin map pages / picker. */
    public static function mapRow(Place $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->t('name'),
            'lat' => (float) $p->latitude,
            'lng' => (float) $p->longitude,
            'icon' => $p->displayIcon(),
            'color' => $p->displayColor(),
            'category' => $p->category?->t('name'),
            'category_id' => $p->category_id,
            'status' => $p->status,
            'featured' => (bool) $p->featured,
            'thumb' => MediaPresenter::image($p->getFirstMedia('cover'))['thumb_url'] ?? null,
            'edit' => route('admin.places.edit', $p),
        ];
    }
}
