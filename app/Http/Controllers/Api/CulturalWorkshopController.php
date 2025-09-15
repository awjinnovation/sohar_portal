<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CulturalWorkshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CulturalWorkshopController extends Controller
{
    public function index()
    {
        $workshops = CulturalWorkshop::where('is_active', true)
            ->with('schedules')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $workshops->items(),
            'pagination' => [
                'current_page' => $workshops->currentPage(),
                'last_page' => $workshops->lastPage(),
                'per_page' => $workshops->perPage(),
                'total' => $workshops->total()
            ]
        ]);
    }

    public function show($id)
    {
        $workshop = CulturalWorkshop::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $workshop
        ]);
    }

    public function available()
    {
        $workshops = CulturalWorkshop::where('is_active', true)
            ->with('schedules')
            ->get()
            ->filter(function ($workshop) {
                return $workshop->schedules->where('date', '>=', now()->toDateString())
                    ->where('available_slots', '>', 'booked_slots')
                    ->count() > 0;
            });

        return response()->json([
            'success' => true,
            'data' => $workshops
        ]);
    }

    public function register(Request $request, $id)
    {
        $workshop = CulturalWorkshop::findOrFail($id);

        if ($workshop->current_participants >= $workshop->max_participants) {
            return response()->json([
                'success' => false,
                'message' => 'Workshop is fully booked'
            ], 400);
        }

        // Check if already registered
        $existing = DB::table('workshop_registrations')
            ->where('user_id', $request->user()->id)
            ->where('workshop_id', $id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Already registered for this workshop'
            ], 400);
        }

        // Register user
        DB::table('workshop_registrations')->insert([
            'user_id' => $request->user()->id,
            'workshop_id' => $id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Increment participants count
        $workshop->increment('current_participants');

        return response()->json([
            'success' => true,
            'message' => 'Successfully registered for workshop'
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $deleted = DB::table('workshop_registrations')
            ->where('user_id', $request->user()->id)
            ->where('workshop_id', $id)
            ->delete();

        if ($deleted) {
            CulturalWorkshop::find($id)->decrement('current_participants');

            return response()->json([
                'success' => true,
                'message' => 'Registration cancelled successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Registration not found'
        ], 404);
    }

    public function myRegistrations(Request $request)
    {
        $workshopIds = DB::table('workshop_registrations')
            ->where('user_id', $request->user()->id)
            ->pluck('workshop_id');

        $workshops = CulturalWorkshop::whereIn('id', $workshopIds)
            ->orderBy('workshop_date')
            ->orderBy('workshop_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workshops
        ]);
    }
}
