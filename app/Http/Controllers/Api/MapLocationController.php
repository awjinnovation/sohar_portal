<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapLocation;
use Illuminate\Http\Request;

class MapLocationController extends Controller
{
    public function index()
    {
        $locations = MapLocation::where('is_active', true)->get();
            
        return response()->json([
            'success' => true,
            'data' => $locations
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
            'data' => $locations
        ]);
    }

    public function entertainment()
    {
        $locations = MapLocation::where('type', 'entertainment')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    public function food()
    {
        $locations = MapLocation::where('type', 'food')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    public function facilities()
    {
        $locations = MapLocation::where('type', 'facilities')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    public function parking()
    {
        $locations = MapLocation::where('type', 'parking')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }
}
