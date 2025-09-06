<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class HeritageVillageController extends Controller
{
    public function index()
    {
        $villages = HeritageVillage::latest()->paginate(10);
        return view("admin.heritage-villages.index", compact("villages"));
    }

    public function create()
    {
        return view("admin.heritage-villages.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name_en" => "required|max:255",
            "name_ar" => "required|max:255",
            "description_en" => "required",
            "description_ar" => "required",
            "type" => "required|in:maritime,agricultural,bedouin",
            "cover_image" => "required|url|max:500",
            "opening_hours" => "required|max:100",
            "virtual_tour_url" => "nullable|url|max:500",
            "is_active" => "boolean"
        ]);

        HeritageVillage::create($validated);

        return redirect()->route("admin.heritage-villages.index")
            ->with("success", "تم إضافة القرية التراثية بنجاح");
    }

    public function show(HeritageVillage $heritageVillage)
    {
        $heritageVillage->load(["images", "attractions", "craftDemonstrations", "traditionalActivities", "culturalWorkshops", "photoSpots"]);
        return view("admin.heritage-villages.show", compact("heritageVillage"));
    }

    public function edit(HeritageVillage $heritageVillage)
    {
        return view("admin.heritage-villages.edit", compact("heritageVillage"));
    }

    public function update(Request $request, HeritageVillage $heritageVillage)
    {
        $validated = $request->validate([
            "name_en" => "required|max:255",
            "name_ar" => "required|max:255",
            "description_en" => "required",
            "description_ar" => "required",
            "type" => "required|in:maritime,agricultural,bedouin",
            "cover_image" => "required|url|max:500",
            "opening_hours" => "required|max:100",
            "virtual_tour_url" => "nullable|url|max:500",
            "is_active" => "boolean"
        ]);

        $heritageVillage->update($validated);

        return redirect()->route("admin.heritage-villages.index")
            ->with("success", "تم تحديث القرية التراثية بنجاح");
    }

    public function destroy(HeritageVillage $heritageVillage)
    {
        $heritageVillage->delete();
        return redirect()->route("admin.heritage-villages.index")
            ->with("success", "تم حذف القرية التراثية بنجاح");
    }
}