<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VillageImage;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VillageImageController extends Controller
{
    public function index()
    {
        $images = VillageImage::with('village')->latest()->paginate(20);
        return view('admin.village-images.index', compact('images'));
    }

    public function create()
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.village-images.create', compact('villages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'image_url' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'caption_en' => 'nullable|string|max:255',
            'caption_ar' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer',
            'is_featured' => 'nullable|boolean'
        ]);

        if ($request->hasFile('image_url')) {
            $path = $request->file('image_url')->store('village-images', 'public');
            $validated['image_url'] = Storage::url($path);
        }

        $validated['is_featured'] = $request->has('is_featured');

        VillageImage::create($validated);

        return redirect()->route('admin.village-images.index')
            ->with('success', 'تم إضافة الصورة بنجاح');
    }

    public function show(VillageImage $villageImage)
    {
        return view('admin.village-images.show', compact('villageImage'));
    }

    public function edit(VillageImage $villageImage)
    {
        $villages = HeritageVillage::active()->get();
        return view('admin.village-images.edit', compact('villageImage', 'villages'));
    }

    public function update(Request $request, VillageImage $villageImage)
    {
        $validated = $request->validate([
            'heritage_village_id' => 'required|exists:heritage_villages,id',
            'image_url' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'caption_en' => 'nullable|string|max:255',
            'caption_ar' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer',
            'is_featured' => 'nullable|boolean'
        ]);

        if ($request->hasFile('image_url')) {
            $path = $request->file('image_url')->store('village-images', 'public');
            $validated['image_url'] = Storage::url($path);
        }

        $validated['is_featured'] = $request->has('is_featured');

        $villageImage->update($validated);

        return redirect()->route('admin.village-images.index')
            ->with('success', 'تم تحديث الصورة بنجاح');
    }

    public function destroy(VillageImage $villageImage)
    {
        $villageImage->delete();
        return redirect()->route('admin.village-images.index')
            ->with('success', 'تم حذف الصورة بنجاح');
    }
}
