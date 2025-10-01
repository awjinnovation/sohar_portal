#!/bin/bash

# VillageAttractionController
cat > app/Http/Controllers/Admin/VillageAttractionController.php << 'EOF'
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
EOF

# CraftDemonstrationController
cat > app/Http/Controllers/Admin/CraftDemonstrationController.php << 'EOF'
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
EOF

# TraditionalActivityController
cat > app/Http/Controllers/Admin/TraditionalActivityController.php << 'EOF'
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
        $activities = TraditionalActivity::with('village')->latest()->paginate(20);
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
EOF

# CulturalWorkshopController
cat > app/Http/Controllers/Admin/CulturalWorkshopController.php << 'EOF'
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
EOF

# PhotoSpotController
cat > app/Http/Controllers/Admin/PhotoSpotController.php << 'EOF'
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
EOF

echo "All Heritage Village related controllers have been implemented!"