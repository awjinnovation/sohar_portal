<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CraftDemonstration;
use Illuminate\Http\Request;

class CraftDemonstrationController extends Controller
{
    public function index()
    {
        $demonstrations = CraftDemonstration::where('is_active', true)
            ->orderBy('demonstration_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $demonstrations
        ]);
    }

    public function show($id)
    {
        $demonstration = CraftDemonstration::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $demonstration
        ]);
    }

    public function live()
    {
        $currentTime = now()->format('H:i:s');

        $demonstrations = CraftDemonstration::where('is_active', true)
            ->where('is_live', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $demonstrations
        ]);
    }
}
