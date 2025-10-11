<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\UserFavorite;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * Get all events
     */
    public function index(Request $request)
    {
        $query = Event::with(['category', 'tags']);

        // Filter by date - check if date falls within event range
        if ($request->has('date')) {
            $date = Carbon::parse($request->date);
            $query->where(function($q) use ($date) {
                $q->whereDate('start_time', '<=', $date)
                  ->whereDate('end_time', '>=', $date);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $statusValue = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $statusValue);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $events = $query->orderBy('start_time')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events->items()),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total()
            ]
        ]);
    }

    /**
     * Get event details
     */
    public function show($id)
    {
        $event = Event::with(['category', 'tags'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new EventResource($event)
        ]);
    }

    /**
     * Get events by category
     */
    public function byCategory($categoryId)
    {
        $events = Event::with(['category', 'tags'])
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events->items()),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total()
            ]
        ]);
    }

    /**
     * Get upcoming events
     */
    public function upcoming()
    {
        $events = Event::with(['category', 'tags'])
            ->where('start_time', '>=', now())
            ->where('is_active', true)
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events)
        ]);
    }

    /**
     * Get today's events (events active today within their date range)
     */
    public function today()
    {
        $today = Carbon::today();
        $events = Event::with(['category', 'tags'])
            ->where(function($q) use ($today) {
                $q->whereDate('start_time', '<=', $today)
                  ->whereDate('end_time', '>=', $today);
            })
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events)
        ]);
    }

    /**
     * Get featured events
     */
    public function featured()
    {
        $events = Event::with(['category', 'tags'])
            ->where('is_featured', true)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events)
        ]);
    }

    /**
     * Toggle favorite status
     */
    public function toggleFavorite(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $user = $request->user();

        $favorite = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_id', $event->id)
            ->where('favoritable_type', Event::class)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $isFavorite = false;
        } else {
            UserFavorite::create([
                'user_id' => $user->id,
                'favoritable_id' => $event->id,
                'favoritable_type' => Event::class
            ]);
            $isFavorite = true;
        }

        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorite,
            'message' => $isFavorite ? 'Added to favorites' : 'Removed from favorites'
        ]);
    }

    /**
     * Get user's favorite events
     */
    public function favorites(Request $request)
    {
        $user = $request->user();

        $favoriteIds = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_type', Event::class)
            ->pluck('favoritable_id');

        $events = Event::with(['category', 'tags'])
            ->whereIn('id', $favoriteIds)
            ->orderBy('start_time')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => EventResource::collection($events->items()),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total()
            ]
        ]);
    }
}