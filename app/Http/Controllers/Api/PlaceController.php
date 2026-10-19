<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Discover\PlaceCategoryResource;
use App\Http\Resources\Discover\PlaceResource;
use App\Models\Place;
use App\Models\PlaceCategory;
use App\Support\Geo;
use App\Support\Localized;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Places to visit (docs/places_content_contract.md §3). Module `places`
 * (route middleware). Only published places of active (or no) categories,
 * scoped to the request tenant; drafts and other tenants' rows are 404.
 */
class PlaceController extends Controller
{
    private function visible()
    {
        return Place::query()->published()
            ->where(fn ($q) => $q->whereNull('category_id')->orWhereHas('category', fn ($c) => $c->where('is_active', true)));
    }

    /** GET /places/categories */
    public function categories()
    {
        $categories = PlaceCategory::query()->active()
            ->withCount(['places as published_places_count' => fn ($q) => $q->published()])
            ->orderBy('sort')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => PlaceCategoryResource::collection($categories)->resolve()]);
    }

    /** GET /places?category=&q=&featured=&near=lat,lng&page=&per_page= */
    public function index(Request $request)
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:100'],
            'featured' => ['nullable', 'in:0,1,true,false'],
            'near' => ['nullable', 'string', 'max:60', 'regex:/^\s*-?\d{1,2}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?\s*$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 20);

        $query = $this->visible()->with(['category', 'media']);
        if (filled($data['category'] ?? null)) {
            $category = PlaceCategory::query()->active()
                ->where(fn ($q) => $q->where('key', $data['category'])->orWhere('id', ctype_digit($data['category']) ? (int) $data['category'] : 0))
                ->first();
            $query->where('category_id', $category?->id ?? 0);
        }
        if (filled($data['q'] ?? null)) {
            $query->searchLocalized($data['q'], ['name', 'summary', 'wilayat', 'address']);
        }
        if (isset($data['featured'])) {
            $query->where('featured', in_array($data['featured'], ['1', 'true'], true));
        }

        if ($near = Geo::parse($data['near'] ?? null)) {
            [$lat, $lng] = $near;
            // Distance in PHP (haversine): portable across sqlite / mysql; a
            // tenant has hundreds of places at most.
            $all = $query->get()->each(function (Place $p) use ($lat, $lng) {
                $p->distance_m = Geo::distanceMeters($lat, $lng, (float) $p->latitude, (float) $p->longitude);
            })->sortBy([['distance_m', 'asc'], ['id', 'asc']])->values();
            $page = (int) ($data['page'] ?? 1);
            $paginator = new LengthAwarePaginator($all->forPage($page, $perPage)->values(), $all->count(), $perPage, $page);
        } else {
            $paginator = $query->orderByDesc('featured')->orderBy('sort')->orderBy('id')->paginate($perPage);
        }

        return $this->paginated($paginator, fn ($p) => (new PlaceResource($p))->resolve());
    }

    /** GET /places/map — compact list of every published place, with ETag. */
    public function map(Request $request)
    {
        $places = $this->visible()->with('category')->orderBy('sort')->orderBy('id')->get();
        $data = $places->map(fn (Place $p) => [
            'id' => $p->id,
            'name' => Localized::pair($p->name),
            'name_text' => $p->t('name'),
            'latitude' => (float) $p->latitude,
            'longitude' => (float) $p->longitude,
            'icon' => $p->displayIcon(),
            'color' => $p->displayColor(),
            'category_key' => $p->category?->key,
            'featured' => (bool) $p->featured,
        ])->values()->all();

        $etag = '"'.sha1(json_encode([app()->getLocale(), $data])).'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'public, max-age=60', 'Vary' => 'X-Tenant, Accept-Language'];
        $ifNoneMatch = array_map('trim', explode(',', (string) $request->header('If-None-Match', '')));
        if (in_array($etag, $ifNoneMatch, true) || in_array('W/'.$etag, $ifNoneMatch, true)) {
            return response('', 304, $headers);
        }

        return response()->json(['success' => true, 'data' => $data], 200, $headers);
    }

    /** GET /places/{id or slug} */
    public function show(string $place)
    {
        $model = $this->visible()->with(['category', 'media'])
            ->where(fn ($q) => ctype_digit($place) ? $q->where('id', (int) $place)->orWhere('slug', $place) : $q->where('slug', $place))
            ->first();
        if (! $model) {
            return $this->notFound();
        }

        return response()->json(['success' => true, 'data' => PlaceResource::detail($model)->resolve()]);
    }

    private function notFound()
    {
        return response()->json(['success' => false, 'message' => __('platform.errors.not_found'), 'code' => 'NOT_FOUND'], 404);
    }

    public static function paginatedResponse(LengthAwarePaginator $p, callable $map)
    {
        return response()->json([
            'success' => true,
            'data' => collect($p->items())->map($map)->values()->all(),
            'pagination' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    private function paginated(LengthAwarePaginator $p, callable $map)
    {
        return self::paginatedResponse($p, $map);
    }
}
