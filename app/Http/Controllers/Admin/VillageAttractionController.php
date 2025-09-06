<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VillageAttraction;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class VillageAttractionController extends Controller
{
    public function index()
    {
        $attractions = VillageAttraction::with('village')->latest()->paginate(20);
        return view('admin.village-attractions.index', compact('attractions'));
    }

    public function create()
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.village-attractions.create', compact('villages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'location_description_en' => 'nullable|string',
            'location_description_ar' => 'nullable|string',
            'visiting_hours' => 'nullable|string|max:255',
            'accessibility_info_en' => 'nullable|string',
            'accessibility_info_ar' => 'nullable|string',
            'recommended_duration' => 'nullable|string|max:100',
            'age_suitability' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        VillageAttraction::create($validated);

        return redirect()->route('admin.village-attractions.index')
            ->with('success', 'تم إضافة المعلم بنجاح');
    }

    public function show(VillageAttraction $villageAttraction)
    {
        return view('admin.village-attractions.show', compact('villageAttraction'));
    }

    public function edit(VillageAttraction $villageAttraction)
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.village-attractions.edit', compact('villageAttraction', 'villages'));
    }

    public function update(Request $request, VillageAttraction $villageAttraction)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'location_description_en' => 'nullable|string',
            'location_description_ar' => 'nullable|string',
            'visiting_hours' => 'nullable|string|max:255',
            'accessibility_info_en' => 'nullable|string',
            'accessibility_info_ar' => 'nullable|string',
            'recommended_duration' => 'nullable|string|max:100',
            'age_suitability' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        $villageAttraction->update($validated);

        return redirect()->route('admin.village-attractions.index')
            ->with('success', 'تم تحديث المعلم بنجاح');
    }

    public function destroy(VillageAttraction $villageAttraction)
    {
        $villageAttraction->delete();
        return redirect()->route('admin.village-attractions.index')
            ->with('success', 'تم حذف المعلم بنجاح');
    }
}
