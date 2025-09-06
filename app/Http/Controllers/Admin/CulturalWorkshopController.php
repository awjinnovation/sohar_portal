<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CulturalWorkshop;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class CulturalWorkshopController extends Controller
{
    public function index()
    {
        $workshops = CulturalWorkshop::with('village')->latest()->paginate(20);
        return view('admin.cultural-workshops.index', compact('workshops'));
    }

    public function create()
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.cultural-workshops.create', compact('villages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'workshop_title_en' => 'required|string|max:255',
            'workshop_title_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'instructor_name' => 'nullable|string|max:255',
            'instructor_bio_en' => 'nullable|string',
            'instructor_bio_ar' => 'nullable|string',
            'schedule' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'max_participants' => 'nullable|integer',
            'min_age' => 'nullable|integer',
            'skill_level' => 'nullable|string|max:50',
            'materials_included' => 'nullable|boolean',
            'price' => 'nullable|numeric',
            'booking_link' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['materials_included'] = $request->has('materials_included');
        $validated['is_active'] = $request->has('is_active');
        CulturalWorkshop::create($validated);

        return redirect()->route('admin.cultural-workshops.index')
            ->with('success', 'تم إضافة الورشة الثقافية بنجاح');
    }

    public function show(CulturalWorkshop $culturalWorkshop)
    {
        return view('admin.cultural-workshops.show', compact('culturalWorkshop'));
    }

    public function edit(CulturalWorkshop $culturalWorkshop)
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.cultural-workshops.edit', compact('culturalWorkshop', 'villages'));
    }

    public function update(Request $request, CulturalWorkshop $culturalWorkshop)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'workshop_title_en' => 'required|string|max:255',
            'workshop_title_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'instructor_name' => 'nullable|string|max:255',
            'instructor_bio_en' => 'nullable|string',
            'instructor_bio_ar' => 'nullable|string',
            'schedule' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'max_participants' => 'nullable|integer',
            'min_age' => 'nullable|integer',
            'skill_level' => 'nullable|string|max:50',
            'materials_included' => 'nullable|boolean',
            'price' => 'nullable|numeric',
            'booking_link' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['materials_included'] = $request->has('materials_included');
        $validated['is_active'] = $request->has('is_active');
        $culturalWorkshop->update($validated);

        return redirect()->route('admin.cultural-workshops.index')
            ->with('success', 'تم تحديث الورشة الثقافية بنجاح');
    }

    public function destroy(CulturalWorkshop $culturalWorkshop)
    {
        $culturalWorkshop->delete();
        return redirect()->route('admin.cultural-workshops.index')
            ->with('success', 'تم حذف الورشة الثقافية بنجاح');
    }
}
