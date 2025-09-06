<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('display_order')->paginate(10);
        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:100',
            'name_ar' => 'required|max:100',
            'description' => 'required',
            'description_ar' => 'required',
            'icon_name' => 'required|max:50',
            'color_value' => 'required|numeric',
            'image_url' => 'nullable|url|max:500',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);

        Category::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'تم إضافة الفئة بنجاح');
    }

    public function show(Category $category)
    {
        $category->load(['events' => function($query) {
            $query->latest()->limit(10);
        }]);
        
        return view('admin.categories.show', compact('category'));
    }

    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|max:100',
            'name_ar' => 'required|max:100',
            'description' => 'required',
            'description_ar' => 'required',
            'icon_name' => 'required|max:50',
            'color_value' => 'required|numeric',
            'image_url' => 'nullable|url|max:500',
            'display_order' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'تم تحديث الفئة بنجاح');
    }

    public function destroy(Category $category)
    {
        if ($category->events()->count() > 0) {
            return redirect()->route('admin.categories.index')
                ->with('error', 'لا يمكن حذف الفئة لوجود فعاليات مرتبطة بها');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'تم حذف الفئة بنجاح');
    }
}