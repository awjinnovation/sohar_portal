<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DiscoverForm;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Admin: content collections (services, partners, guides…), permission `manage content`. */
class ContentCollectionController extends Controller
{
    use DiscoverForm;

    public function index()
    {
        $collections = ContentCollection::withCount([
            'items',
            'items as published_items_count' => fn ($q) => $q->where('status', 'published'),
        ])->orderBy('sort')->orderBy('id')->get();

        return view('admin.content-collections.index', compact('collections'));
    }

    public function create()
    {
        return view('admin.content-collections.form', ['collection' => new ContentCollection(['layout' => 'list', 'show_in_discover' => true, 'is_active' => true, 'icon' => 'list'])]);
    }

    public function edit(ContentCollection $collection)
    {
        return view('admin.content-collections.form', compact('collection'));
    }

    private function rules(?ContentCollection $collection = null): array
    {
        $unique = $this->tenantUnique('content_collections', 'key');

        return array_merge(
            $this->localizedRules('name', 100, true),
            $this->localizedRules('description', 500),
            $this->iconRules(),
            [
                'key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $collection ? $unique->ignore($collection->id) : $unique],
                'layout' => ['required', 'in:'.implode(',', ContentCollection::LAYOUTS)],
                'show_in_discover' => ['nullable', 'boolean'],
                'is_active' => ['nullable', 'boolean'],
                'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
            ],
        );
    }

    private function save(Request $request, ContentCollection $collection): ContentCollection
    {
        $name = $this->localized($request, 'name');
        $key = $request->input('key') ?: ($collection->key ?: Str::slug($name['en'] ?? $name['ar'] ?? '') ?: 'collection');
        $base = $key;
        $n = 2;
        while (ContentCollection::where('key', $key)->when($collection->exists, fn ($q) => $q->where('id', '!=', $collection->id))->exists()) {
            $key = $base.'-'.$n++;
        }

        $collection->fill([
            'key' => $key,
            'name' => $name,
            'description' => $this->localized($request, 'description'),
            'icon' => $request->input('icon') ?: null,
            'layout' => $request->input('layout'),
            'show_in_discover' => $request->boolean('show_in_discover'),
            'is_active' => $request->boolean('is_active'),
            'sort' => $request->filled('sort') ? (int) $request->input('sort') : ($collection->sort ?? ((int) ContentCollection::max('sort') + 1)),
        ])->save();

        return $collection;
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());
        $collection = $this->save($request, new ContentCollection);

        return redirect()->route('admin.content-items.index', $collection)->with('success', 'أُضيفت المجموعة');
    }

    public function update(Request $request, ContentCollection $collection)
    {
        $request->validate($this->rules($collection));
        $this->save($request, $collection);

        return redirect()->route('admin.content-collections.index')->with('success', 'حُفظت المجموعة');
    }

    public function destroy(ContentCollection $collection)
    {
        $collection->items()->get()->each->delete(); // media rows go with each item
        $collection->delete();

        return redirect()->route('admin.content-collections.index')->with('success', 'حُذفت المجموعة');
    }

    public function reorder(Request $request)
    {
        return $this->reorderRows($request, ContentCollection::class);
    }
}
