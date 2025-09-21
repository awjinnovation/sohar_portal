<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TraditionalActivity;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class TraditionalActivityController extends Controller
{
    public function index()
    {
        $activities = TraditionalActivity::with('heritageVillage')->latest()->paginate(20);
        return view('admin.traditional-activities.index', compact('activities'));
    }

    public function create()
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.traditional-activities.create', compact('villages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'activity_name_en' => 'required|string|max:255',
            'activity_name_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'activity_type' => 'required|string|max:100',
            'schedule' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'max_participants' => 'nullable|integer',
            'age_restrictions' => 'nullable|string|max:100',
            'equipment_provided' => 'nullable|boolean',
            'cultural_significance_en' => 'nullable|string',
            'cultural_significance_ar' => 'nullable|string',
            'booking_required' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['equipment_provided'] = $request->has('equipment_provided');
        $validated['booking_required'] = $request->has('booking_required');
        $validated['is_active'] = $request->has('is_active');
        TraditionalActivity::create($validated);

        return redirect()->route('admin.traditional-activities.index')
            ->with('success', 'تم إضافة النشاط التقليدي بنجاح');
    }

    public function show(TraditionalActivity $traditionalActivity)
    {
        return view('admin.traditional-activities.show', compact('traditionalActivity'));
    }

    public function edit(TraditionalActivity $traditionalActivity)
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.traditional-activities.edit', compact('traditionalActivity', 'villages'));
    }

    public function update(Request $request, TraditionalActivity $traditionalActivity)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'activity_name_en' => 'required|string|max:255',
            'activity_name_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'activity_type' => 'required|string|max:100',
            'schedule' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'max_participants' => 'nullable|integer',
            'age_restrictions' => 'nullable|string|max:100',
            'equipment_provided' => 'nullable|boolean',
            'cultural_significance_en' => 'nullable|string',
            'cultural_significance_ar' => 'nullable|string',
            'booking_required' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['equipment_provided'] = $request->has('equipment_provided');
        $validated['booking_required'] = $request->has('booking_required');
        $validated['is_active'] = $request->has('is_active');
        $traditionalActivity->update($validated);

        return redirect()->route('admin.traditional-activities.index')
            ->with('success', 'تم تحديث النشاط التقليدي بنجاح');
    }

    public function destroy(TraditionalActivity $traditionalActivity)
    {
        $traditionalActivity->delete();
        return redirect()->route('admin.traditional-activities.index')
            ->with('success', 'تم حذف النشاط التقليدي بنجاح');
    }
}
