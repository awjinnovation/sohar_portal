<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $announcements->items(),
            'pagination' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total()
            ]
        ]);
    }

    public function active()
    {
        $announcements = Announcement::where('is_active', true)
            ->where(function($query) {
                $query->whereNull('start_datetime')
                    ->orWhere('start_datetime', '<=', now());
            })
            ->where(function($query) {
                $query->whereNull('end_datetime')
                    ->orWhere('end_datetime', '>=', now());
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $announcements
        ]);
    }

    public function show($id)
    {
        $announcement = Announcement::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $announcement
        ]);
    }
}