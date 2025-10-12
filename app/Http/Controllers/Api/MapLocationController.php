<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\LocaleHelper;
use App\Models\MapLocation;
use Illuminate\Http\Request;

class MapLocationController extends Controller
{
    public function index()
    {
        $locations = MapLocation::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function($location) {
                return $this->formatLocation($location);
            })
        ]);
    }

    public function types()
    {
        return response()->json([
            'success' => true,
            'data' => MapLocation::getTypes()
        ]);
    }

    public function byCategory($category)
    {
        $locations = MapLocation::where('category', $category)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function($location) {
                return $this->formatLocation($location);
            })
        ]);
    }

    public function entertainment()
    {
        $locations = MapLocation::where('type', 'entertainment')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function($location) {
                return $this->formatLocation($location);
            })
        ]);
    }

    public function food()
    {
        $locations = MapLocation::where('type', 'food')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function($location) {
                return $this->formatLocation($location);
            })
        ]);
    }

    public function facilities()
    {
        $locations = MapLocation::where('type', 'facilities')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function($location) {
                return $this->formatLocation($location);
            })
        ]);
    }

    public function parking()
    {
        $locations = MapLocation::where('type', 'parking')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations->map(function($location) {
                return $this->formatLocation($location);
            })
        ]);
    }

    private function formatLocation($location)
    {
        $locale = LocaleHelper::getLocale();

        return [
            'id' => $location->id,
            'name' => LocaleHelper::getLocalizedField($location, 'name'),
            'description' => LocaleHelper::getLocalizedField($location, 'description'),
            'type' => $location->type,
            'type_translation' => $location->getTypeTranslation($locale),
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'icon' => $location->icon,
            'color' => $location->color,
            'is_active' => $location->is_active,
        ];
    }
}
