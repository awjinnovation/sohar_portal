<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotoSpot;
use Illuminate\Http\Request;

class PhotoSpotController extends Controller
{
    public function index()
    {
        $spots = PhotoSpot::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $spots
        ]);
    }

    public function show($id)
    {
        $spot = PhotoSpot::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $spot
        ]);
    }
}
