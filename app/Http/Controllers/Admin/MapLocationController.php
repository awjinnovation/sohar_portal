<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MapLocation;
use Illuminate\Http\Request;

class MapLocationController extends Controller
{
    public function index(Request $request)
    {
        $query = MapLocation::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('name_ar', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('description_ar', 'like', '%' . $search . '%');
            });
        }

        $locations = $query->orderBy('type')->orderBy('name')->paginate(15);

        return view('admin.map-locations.index', compact('locations'));
    }

    public function create()
    {
        return view('admin.map-locations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'type' => 'required|in:stage,restaurant,parking,info,shopping,restroom,facilities,entertainment,food,emergency,first_aid,heritage,fort,beach,venue',
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'icon' => 'required|string|max:50',
            'color' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');

        MapLocation::create($validated);

        return redirect()->route('admin.map-locations.index')
                        ->with('success', 'Map location created successfully');
    }

    public function show(MapLocation $mapLocation)
    {
        return view('admin.map-locations.show', compact('mapLocation'));
    }

    public function edit(MapLocation $mapLocation)
    {
        return view('admin.map-locations.edit', compact('mapLocation'));
    }

    public function update(Request $request, MapLocation $mapLocation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'type' => 'required|in:stage,restaurant,parking,info,shopping,restroom,facilities,entertainment,food,emergency,first_aid,heritage,fort,beach,venue',
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'icon' => 'required|string|max:50',
            'color' => 'nullable|integer',
            'is_active' => 'boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');

        $mapLocation->update($validated);

        return redirect()->route('admin.map-locations.index')
                        ->with('success', 'Map location updated successfully');
    }

    public function destroy(MapLocation $mapLocation)
    {
        $mapLocation->delete();

        return redirect()->route('admin.map-locations.index')
                        ->with('success', 'Map location deleted successfully');
    }
}