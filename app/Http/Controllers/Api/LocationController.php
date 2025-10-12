<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapLocation;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get all locations
     */
    public function index(Request $request)
    {
        $query = MapLocation::where('is_active', true);

        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $locations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $locations->map(function ($location) {
                return $this->formatLocation($location);
            }),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ],
            'message' => 'Locations retrieved successfully'
        ]);
    }

    /**
     * Get locations by type
     */
    public function byType($type)
    {
        $locations = MapLocation::where('type', $type)
                             ->where('is_active', true)
                             ->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function ($location) {
                return $this->formatLocation($location);
            }),
            'message' => 'Locations retrieved successfully'
        ]);
    }

    /**
     * Get emergency locations
     */
    public function emergency()
    {
        return $this->byType('emergency');
    }

    /**
     * Get first aid stations
     */
    public function firstAid()
    {
        return $this->byType('first_aid');
    }

    /**
     * Get parking locations
     */
    public function parking()
    {
        return $this->byType('parking');
    }

    /**
     * Get restroom locations
     */
    public function restrooms()
    {
        return $this->byType('restroom');
    }

    /**
     * Get nearby locations
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'nullable|numeric|min:0.1|max:10', // radius in km
        ]);

        $lat = $request->input('latitude');
        $lng = $request->input('longitude');
        $radius = $request->input('radius', 1); // default 1km

        // Haversine formula for distance calculation
        $locations = MapLocation::selectRaw("
                *,
                (6371 * acos(cos(radians(?))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians(?))
                + sin(radians(?))
                * sin(radians(latitude)))) AS distance
            ", [$lat, $lng, $lat])
            ->where('is_active', true)
            ->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function ($location) {
                $data = $this->formatLocation($location);
                $data['distance'] = round($location->distance, 2); // km
                return $data;
            }),
            'message' => 'Nearby locations retrieved successfully'
        ]);
    }

    /**
     * Get single location
     */
    public function show($id)
    {
        $location = MapLocation::find($id);

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatLocation($location),
            'message' => 'Location retrieved successfully'
        ]);
    }

    /**
     * Format location data
     */
    private function formatLocation($location)
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'name_ar' => $location->name_ar,
            'type' => $location->type,
            'description' => $location->description,
            'description_ar' => $location->description_ar,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'icon' => $location->icon,
            'color' => $location->color,
            'is_active' => $location->is_active,
        ];
    }
}
