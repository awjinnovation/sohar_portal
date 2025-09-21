<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FirstAidStation;
use Illuminate\Http\Request;

class FirstAidStationController extends Controller
{
    public function index()
    {
        $stations = FirstAidStation::paginate(10);
        return view('admin.first-aid-stations.index', compact('stations'));
    }

    public function create()
    {
        return view('admin.first-aid-stations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'location_ar' => 'required|string|max:255',
            'contact_number' => 'required|string|max:255',
            'available_services' => 'nullable|string',
            'available_services_ar' => 'nullable|string'
        ]);

        FirstAidStation::create($validated);

        return redirect()->route('admin.first-aid-stations.index')
            ->with('success', 'تم إضافة محطة الإسعاف الأولي بنجاح');
    }

    public function show(FirstAidStation $firstAidStation)
    {
        return view('admin.first-aid-stations.show', compact('firstAidStation'));
    }

    public function edit(FirstAidStation $firstAidStation)
    {
        return view('admin.first-aid-stations.edit', compact('firstAidStation'));
    }

    public function update(Request $request, FirstAidStation $firstAidStation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'location_ar' => 'required|string|max:255',
            'contact_number' => 'required|string|max:255',
            'available_services' => 'nullable|string',
            'available_services_ar' => 'nullable|string'
        ]);

        $firstAidStation->update($validated);

        return redirect()->route('admin.first-aid-stations.index')
            ->with('success', 'تم تحديث محطة الإسعاف الأولي بنجاح');
    }

    public function destroy(FirstAidStation $firstAidStation)
    {
        $firstAidStation->delete();

        return redirect()->route('admin.first-aid-stations.index')
            ->with('success', 'تم حذف محطة الإسعاف الأولي بنجاح');
    }
}