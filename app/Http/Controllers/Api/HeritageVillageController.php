<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class HeritageVillageController extends Controller
{
    public function index()
    {
        $villages = HeritageVillage::with(['images', 'attractions'])
            ->where('status', 'active')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $villages
        ]);
    }

    public function show($id)
    {
        $village = HeritageVillage::with(['images', 'attractions'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $village
        ]);
    }
}
