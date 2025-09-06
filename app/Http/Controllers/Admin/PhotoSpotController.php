<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoSpot;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class PhotoSpotController extends Controller
{
    public function index()
    {
        $photoSpots = PhotoSpot::with('village')->latest()->paginate(20);
        return view('admin.photo-spots.index', compact('photoSpots'));
    }

    public function create()
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.photo-spots.create', compact('villages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'spot_name_en' => 'required|string|max:255',
            'spot_name_ar' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'location_details_en' => 'nullable|string',
            'location_details_ar' => 'nullable|string',
            'best_time_for_photos' => 'nullable|string|max:255',
            'photography_tips_en' => 'nullable|string',
            'photography_tips_ar' => 'nullable|string',
            'hashtags' => 'nullable|string|max:500',
            'is_accessible' => 'nullable|boolean',
            'has_backdrop' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_accessible'] = $request->has('is_accessible');
        $validated['has_backdrop'] = $request->has('has_backdrop');
        $validated['is_popular'] = $request->has('is_popular');
        $validated['is_active'] = $request->has('is_active');
        PhotoSpot::create($validated);

        return redirect()->route('admin.photo-spots.index')
            ->with('success', 'تم إضافة موقع التصوير بنجاح');
    }

    public function show(PhotoSpot $photoSpot)
    {
        return view('admin.photo-spots.show', compact('photoSpot'));
    }

    public function edit(PhotoSpot $photoSpot)
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.photo-spots.edit', compact('photoSpot', 'villages'));
    }

    public function update(Request $request, PhotoSpot $photoSpot)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'spot_name_en' => 'required|string|max:255',
            'spot_name_ar' => 'required|string|max:255',
            'description_en' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'location_details_en' => 'nullable|string',
            'location_details_ar' => 'nullable|string',
            'best_time_for_photos' => 'nullable|string|max:255',
            'photography_tips_en' => 'nullable|string',
            'photography_tips_ar' => 'nullable|string',
            'hashtags' => 'nullable|string|max:500',
            'is_accessible' => 'nullable|boolean',
            'has_backdrop' => 'nullable|boolean',
            'is_popular' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_accessible'] = $request->has('is_accessible');
        $validated['has_backdrop'] = $request->has('has_backdrop');
        $validated['is_popular'] = $request->has('is_popular');
        $validated['is_active'] = $request->has('is_active');
        $photoSpot->update($validated);

        return redirect()->route('admin.photo-spots.index')
            ->with('success', 'تم تحديث موقع التصوير بنجاح');
    }

    public function destroy(PhotoSpot $photoSpot)
    {
        $photoSpot->delete();
        return redirect()->route('admin.photo-spots.index')
            ->with('success', 'تم حذف موقع التصوير بنجاح');
    }
}
