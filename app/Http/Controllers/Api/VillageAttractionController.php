<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VillageAttraction;
use Illuminate\Http\Request;

class VillageAttractionController extends Controller
{
    public function index()
    {
        $attractions = VillageAttraction::with('village')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attractions
        ]);
    }

    public function show($id)
    {
        $attraction = VillageAttraction::with('village')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $attraction
        ]);
    }

    public function byVillage($villageId)
    {
        $attractions = VillageAttraction::where('village_id', $villageId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attractions
        ]);
    }
}
