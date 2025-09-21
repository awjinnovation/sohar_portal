<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocationCategory;
use Illuminate\Http\Request;

class LocationCategoryController extends Controller
{
    public function index()
    {
        $categories = LocationCategory::paginate(10);
        return view('admin.location-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.location-categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string'
        ]);

        LocationCategory::create($validated);

        return redirect()->route('admin.location-categories.index')
            ->with('success', 'تم إضافة فئة الموقع بنجاح');
    }

    public function show(LocationCategory $locationCategory)
    {
        return view('admin.location-categories.show', compact('locationCategory'));
    }

    public function edit(LocationCategory $locationCategory)
    {
        return view('admin.location-categories.edit', compact('locationCategory'));
    }

    public function update(Request $request, LocationCategory $locationCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string'
        ]);

        $locationCategory->update($validated);

        return redirect()->route('admin.location-categories.index')
            ->with('success', 'تم تحديث فئة الموقع بنجاح');
    }

    public function destroy(LocationCategory $locationCategory)
    {
        $locationCategory->delete();

        return redirect()->route('admin.location-categories.index')
            ->with('success', 'تم حذف فئة الموقع بنجاح');
    }
}