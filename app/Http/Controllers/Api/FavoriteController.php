<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFavorite;
use App\Models\Event;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Get all user favorites
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $favorites = UserFavorite::where('user_id', $user->id)
            ->with(['favoritable'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favorites->map(function ($favorite) {
                return [
                    'id' => $favorite->id,
                    'type' => $favorite->favoritable_type,
                    'item_id' => $favorite->favoritable_id,
                    'item' => $this->formatFavoriteItem($favorite->favoritable),
                    'added_at' => $favorite->created_at,
                ];
            }),
            'message' => 'Favorites retrieved successfully'
        ]);
    }

    /**
     * Get user's favorite events
     */
    public function events()
    {
        $user = Auth::user();

        $favorites = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_type', Event::class)
            ->with(['favoritable'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favorites->map(function ($favorite) {
                return [
                    'id' => $favorite->id,
                    'event' => $this->formatEvent($favorite->favoritable),
                    'added_at' => $favorite->created_at,
                ];
            }),
            'message' => 'Favorite events retrieved successfully'
        ]);
    }

    /**
     * Get user's favorite restaurants
     */
    public function restaurants()
    {
        $user = Auth::user();

        $favorites = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_type', Restaurant::class)
            ->with(['favoritable'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favorites->map(function ($favorite) {
                return [
                    'id' => $favorite->id,
                    'restaurant' => $this->formatRestaurant($favorite->favoritable),
                    'added_at' => $favorite->created_at,
                ];
            }),
            'message' => 'Favorite restaurants retrieved successfully'
        ]);
    }

    /**
     * Toggle favorite (add or remove)
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'type' => 'required|in:event,restaurant',
            'item_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $type = $request->input('type');
        $itemId = $request->input('item_id');

        // Determine the model class
        $modelClass = $type === 'event' ? Event::class : Restaurant::class;

        // Check if item exists
        $item = $modelClass::find($itemId);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($type) . ' not found'
            ], 404);
        }

        // Check if already favorited
        $favorite = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_type', $modelClass)
            ->where('favoritable_id', $itemId)
            ->first();

        if ($favorite) {
            // Remove favorite
            $favorite->delete();

            return response()->json([
                'success' => true,
                'action' => 'removed',
                'is_favorite' => false,
                'message' => ucfirst($type) . ' removed from favorites'
            ]);
        } else {
            // Add favorite
            $favorite = UserFavorite::create([
                'user_id' => $user->id,
                'favoritable_type' => $modelClass,
                'favoritable_id' => $itemId,
            ]);

            return response()->json([
                'success' => true,
                'action' => 'added',
                'is_favorite' => true,
                'data' => [
                    'id' => $favorite->id,
                    'type' => $type,
                    'item_id' => $itemId,
                    'added_at' => $favorite->created_at,
                ],
                'message' => ucfirst($type) . ' added to favorites'
            ]);
        }
    }

    /**
     * Remove favorite
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $favorite = UserFavorite::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$favorite) {
            return response()->json([
                'success' => false,
                'message' => 'Favorite not found'
            ], 404);
        }

        $favorite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Favorite removed successfully'
        ]);
    }

    /**
     * Format favorite item based on type
     */
    private function formatFavoriteItem($item)
    {
        if (!$item) {
            return null;
        }

        if ($item instanceof Event) {
            return $this->formatEvent($item);
        } elseif ($item instanceof Restaurant) {
            return $this->formatRestaurant($item);
        }

        return null;
    }

    /**
     * Format event data
     */
    private function formatEvent($event)
    {
        if (!$event) {
            return null;
        }

        return [
            'id' => $event->id,
            'title' => $event->title,
            'title_ar' => $event->title_ar,
            'description' => $event->description,
            'description_ar' => $event->description_ar,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'location' => $event->location,
            'location_ar' => $event->location_ar,
            'image_url' => $event->image_url,
            'price' => $event->price,
            'currency' => $event->currency,
            'is_featured' => $event->is_featured,
        ];
    }

    /**
     * Format restaurant data
     */
    private function formatRestaurant($restaurant)
    {
        if (!$restaurant) {
            return null;
        }

        return [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'name_ar' => $restaurant->name_ar,
            'description' => $restaurant->description,
            'description_ar' => $restaurant->description_ar,
            'cuisine' => $restaurant->cuisine,
            'cuisine_ar' => $restaurant->cuisine_ar,
            'location' => $restaurant->location,
            'location_ar' => $restaurant->location_ar,
            'image_url' => $restaurant->image_url,
            'rating' => $restaurant->rating,
            'price_range' => $restaurant->price_range,
            'is_featured' => $restaurant->is_featured,
        ];
    }
}
