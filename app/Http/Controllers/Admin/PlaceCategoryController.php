<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DiscoverForm;
use App\Http\Controllers\Controller;
use App\Models\PlaceCategory;
use App\Support\ColorGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Admin: place categories (icon + colour), permission `manage places`. */
class PlaceCategoryController extends Controller
{
    use DiscoverForm;

    public function index()
    {
        $categories = PlaceCategory::withCount('places')->orderBy('sort')->orderBy('id')->get();

        return view('admin.place-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.place-categories.form', ['category' => new PlaceCategory(['icon' => 'place', 'color' => '#2F8E80', 'is_active' => true])]);
    }

    public function edit(PlaceCategory $placeCategory)
    {
        return view('admin.place-categories.form', ['category' => $placeCategory]);
    }

    private function rules(?PlaceCategory $category = null): array
    {
        $unique = $this->tenantUnique('place_categories', 'key');

        return array_merge($this->localizedRules('name', 80, true), [
            'key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $category ? $unique->ignore($category->id) : $unique],
            'icon' => ['required', 'string', 'max:64', 'regex:'.\App\Support\MaterialIcons::PATTERN],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function save(Request $request, PlaceCategory $category)
    {
        $name = $this->localized($request, 'name');
        $key = $request->input('key') ?: ($category->key ?: Str::slug($name['en'] ?? $name['ar'] ?? '') ?: 'category');
        $base = $key;
        $n = 2;
        while (PlaceCategory::where('key', $key)->when($category->exists, fn ($q) => $q->where('id', '!=', $category->id))->exists()) {
            $key = $base.'-'.$n++;
        }

        $category->fill([
            'key' => $key,
            'name' => $name,
            'icon' => $request->input('icon'),
            'color' => ColorGuard::normalize($request->input('color')) ?? '#2F8E80',
            'sort' => $request->filled('sort') ? (int) $request->input('sort') : ($category->sort ?? ((int) PlaceCategory::max('sort') + 1)),
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return $category;
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());
        $this->save($request, new PlaceCategory);

        return redirect()->route('admin.place-categories.index')->with('success', 'أُضيفت الفئة');
    }

    public function update(Request $request, PlaceCategory $placeCategory)
    {
        $request->validate($this->rules($placeCategory));
        $this->save($request, $placeCategory);

        return redirect()->route('admin.place-categories.index')->with('success', 'حُفظت الفئة');
    }

    public function destroy(PlaceCategory $placeCategory)
    {
        // Places keep existing without a category (category_id → null).
        $placeCategory->delete();

        return redirect()->route('admin.place-categories.index')->with('success', 'حُذفت الفئة');
    }

    public function reorder(Request $request)
    {
        return $this->reorderRows($request, PlaceCategory::class);
    }
}
