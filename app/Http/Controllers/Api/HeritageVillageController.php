<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class HeritageVillageController extends Controller
{
    public function index()
    {
        $villages = HeritageVillage::where('is_active', true)
            ->orderByDesc('is_featured')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $villages
        ]);
    }

    public function show($id)
    {
        $village = HeritageVillage::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $village
        ]);
    }
}
