<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CraftDemonstration;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class CraftDemonstrationController extends Controller
{
    public function index()
    {
        $demonstrations = CraftDemonstration::with('village')->latest()->paginate(20);
        return view('admin.craft-demonstrations.index', compact('demonstrations'));
    }

    public function create()
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.craft-demonstrations.create', compact('villages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'craft_name_en' => 'required|string|max:255',
            'craft_name_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'artisan_name' => 'nullable|string|max:255',
            'demonstration_times' => 'nullable|string',
            'materials_used_en' => 'nullable|string',
            'materials_used_ar' => 'nullable|string',
            'historical_significance_en' => 'nullable|string',
            'historical_significance_ar' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'can_try_hands_on' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['can_try_hands_on'] = $request->has('can_try_hands_on');
        $validated['is_active'] = $request->has('is_active');
        CraftDemonstration::create($validated);

        return redirect()->route('admin.craft-demonstrations.index')
            ->with('success', 'تم إضافة العرض الحرفي بنجاح');
    }

    public function show(CraftDemonstration $craftDemonstration)
    {
        return view('admin.craft-demonstrations.show', compact('craftDemonstration'));
    }

    public function edit(CraftDemonstration $craftDemonstration)
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.craft-demonstrations.edit', compact('craftDemonstration', 'villages'));
    }

    public function update(Request $request, CraftDemonstration $craftDemonstration)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'craft_name_en' => 'required|string|max:255',
            'craft_name_ar' => 'required|string|max:255',
            'description_en' => 'required|string',
            'description_ar' => 'required|string',
            'artisan_name' => 'nullable|string|max:255',
            'demonstration_times' => 'nullable|string',
            'materials_used_en' => 'nullable|string',
            'materials_used_ar' => 'nullable|string',
            'historical_significance_en' => 'nullable|string',
            'historical_significance_ar' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'can_try_hands_on' => 'nullable|boolean',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['can_try_hands_on'] = $request->has('can_try_hands_on');
        $validated['is_active'] = $request->has('is_active');
        $craftDemonstration->update($validated);

        return redirect()->route('admin.craft-demonstrations.index')
            ->with('success', 'تم تحديث العرض الحرفي بنجاح');
    }

    public function destroy(CraftDemonstration $craftDemonstration)
    {
        $craftDemonstration->delete();
        return redirect()->route('admin.craft-demonstrations.index')
            ->with('success', 'تم حذف العرض الحرفي بنجاح');
    }
}
