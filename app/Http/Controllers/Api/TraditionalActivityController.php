<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TraditionalActivity;
use Illuminate\Http\Request;

class TraditionalActivityController extends Controller
{
    public function index()
    {
        $activities = TraditionalActivity::where('is_active', true)
            ->orderBy('activity_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    public function show($id)
    {
        $activity = TraditionalActivity::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $activity
        ]);
    }

    public function interactive()
    {
        $activities = TraditionalActivity::where('is_active', true)
            ->where('is_interactive', true)
            ->orderBy('timing')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }
}
