<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MapLocation;
use Illuminate\Http\Request;

class MapLocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $mapLocations = MapLocation::orderBy('name')->paginate(20);

        return view('admin.map-locations.index', compact('mapLocations'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $types = ['entertainment', 'food', 'facilities', 'parking', 'entrance', 'emergency'];

        return view('admin.map-locations.create', compact('types'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:entertainment,food,facilities,parking,entrance,emergency',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean'
        ]);

        $data = $request->all();

        // Convert hex color to integer if provided
        if ($request->filled('color')) {
            $data['color'] = hexdec(str_replace('#', '', $request->color));
        }

        MapLocation::create($data);

        return redirect()->route('admin.map-locations.index')
            ->with('success', 'Map location created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MapLocation $mapLocation)
    {
        return view('admin.map-locations.show', compact('mapLocation'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MapLocation $mapLocation)
    {
        $types = ['entertainment', 'food', 'facilities', 'parking', 'entrance', 'emergency'];

        return view('admin.map-locations.edit', compact('mapLocation', 'types'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MapLocation $mapLocation)
    {
        $request->validate([
            'type' => 'required|in:entertainment,food,facilities,parking,entrance,emergency',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean'
        ]);

        $data = $request->all();

        // Convert hex color to integer if provided
        if ($request->filled('color')) {
            $data['color'] = hexdec(str_replace('#', '', $request->color));
        }

        $mapLocation->update($data);

        return redirect()->route('admin.map-locations.index')
            ->with('success', 'Map location updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MapLocation $mapLocation)
    {
        $mapLocation->delete();

        return redirect()->route('admin.map-locations.index')
            ->with('success', 'Map location deleted successfully.');
    }
}
