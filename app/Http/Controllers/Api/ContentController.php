<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Discover\ContentCollectionResource;
use App\Http\Resources\Discover\ContentItemResource;
use App\Http\Resources\Discover\ContentPageResource;
use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\ContentPage;
use Illuminate\Http\Request;

/**
 * Content collections, items and pages (docs/places_content_contract.md §3).
 * Module `content` (route middleware); only published rows of active
 * collections of the request tenant.
 */
class ContentController extends Controller
{
    private function items()
    {
        return ContentItem::query()->published()->whereHas('collection', fn ($c) => $c->where('is_active', true));
    }

    /** GET /content/collections */
    public function collections()
    {
        $collections = ContentCollection::query()->active()
            ->withCount(['items as published_items_count' => fn ($q) => $q->published()])
            ->orderBy('sort')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => ContentCollectionResource::collection($collections)->resolve()]);
    }

    /** GET /content/collections/{key}/items?q=&tag=&page=&per_page= */
    public function collectionItems(Request $request, string $key)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $collection = ContentCollection::query()->active()->where('key', $key)->first();
        if (! $collection) {
            return $this->notFound();
        }

        $query = $this->items()->where('collection_id', $collection->id)->with(['collection', 'media']);
        if (filled($data['q'] ?? null)) {
            $query->searchLocalized($data['q'], ['title', 'subtitle', 'tags']);
        }
        if (filled($data['tag'] ?? null)) {
            $tag = $data['tag'];
            // Tags are a small JSON list: match in PHP-independent SQL with LIKE on the raw JSON, then verify.
            $query->where('tags', 'like', '%'.str_replace(['%', '_'], '', $tag).'%');
        }
        $paginator = $query->orderByDesc('featured')->orderBy('sort')->orderBy('id')->paginate((int) ($data['per_page'] ?? 30));
        if (filled($data['tag'] ?? null)) {
            $paginator->setCollection($paginator->getCollection()->filter(fn ($i) => in_array($data['tag'], (array) $i->tags, true))->values());
        }

        return PlaceController::paginatedResponse($paginator, fn ($i) => (new ContentItemResource($i))->resolve());
    }

    /** GET /content/items/{id} */
    public function item(string $id)
    {
        $item = ctype_digit($id) ? $this->items()->with(['collection', 'media', 'place.category', 'place.media'])->find((int) $id) : null;
        if (! $item) {
            return $this->notFound();
        }

        return response()->json(['success' => true, 'data' => ContentItemResource::detail($item)->resolve()]);
    }

    /** GET /content/pages */
    public function pages()
    {
        $pages = ContentPage::query()->published()->with('media')->orderBy('sort')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => ContentPageResource::collection($pages)->resolve()]);
    }

    /** GET /content/pages/{slug} */
    public function page(string $slug)
    {
        $page = ContentPage::query()->published()->with('media')->where('slug', $slug)->first();
        if (! $page) {
            return $this->notFound();
        }

        return response()->json(['success' => true, 'data' => ContentPageResource::detail($page)->resolve()]);
    }

    private function notFound()
    {
        return response()->json(['success' => false, 'message' => __('platform.errors.not_found'), 'code' => 'NOT_FOUND'], 404);
    }
}
