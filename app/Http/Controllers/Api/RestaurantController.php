<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\LocaleHelper;
use App\Models\Restaurant;
use App\Models\UserFavorite;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index(Request $request)
    {
        $query = Restaurant::query();

        if ($request->has('cuisine')) {
            $query->where('cuisine', $request->cuisine);
        }

        if ($request->has('is_vegetarian')) {
            $query->where('is_vegetarian', $request->is_vegetarian);
        }

        if ($request->has('is_family_friendly')) {
            $query->where('is_family_friendly', $request->is_family_friendly);
        }

        $restaurants = $query->where('is_active', true)->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $restaurants->map(function($restaurant) {
                return $this->formatRestaurant($restaurant);
            }),
            'pagination' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total()
            ]
        ]);
    }

    public function show($id)
    {
        $restaurant = Restaurant::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatRestaurant($restaurant)
        ]);
    }

    private function formatRestaurant($restaurant)
    {
        return [
            'id' => $restaurant->id,
            'name' => LocaleHelper::getLocalizedField($restaurant, 'name'),
            'description' => LocaleHelper::getLocalizedField($restaurant, 'description'),
            'address' => LocaleHelper::getLocalizedField($restaurant, 'address'),
            'cuisine' => LocaleHelper::getLocalizedField($restaurant, 'cuisine'),
            'phone' => $restaurant->phone,
            'email' => $restaurant->email,
            'website' => $restaurant->website,
            'latitude' => $restaurant->latitude ? (float) $restaurant->latitude : null,
            'longitude' => $restaurant->longitude ? (float) $restaurant->longitude : null,
            'rating' => $restaurant->rating ? (float) $restaurant->rating : null,
            'price_range' => $restaurant->price_range,
            'is_vegetarian' => (bool) $restaurant->is_vegetarian,
            'is_halal' => (bool) $restaurant->is_halal,
            'is_family_friendly' => (bool) $restaurant->is_family_friendly,
            'is_active' => (bool) $restaurant->is_active,
        ];
    }

    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $restaurants = Restaurant::with(['images', 'features'])
            ->where('is_active', true)
            ->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query . '%')
                  ->orWhere('description', 'like', '%' . $request->query . '%')
                  ->orWhere('cuisine', 'like', '%' . $request->query . '%');
            })
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $restaurants->items(),
            'pagination' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total()
            ]
        ]);
    }

    public function openNow()
    {
        $currentTime = now()->format('H:i:s');
        $currentDay = strtolower(now()->format('l'));

        $restaurants = Restaurant::with(['images', 'features', 'openingHours'])
            ->where('is_active', true)
            ->whereHas('openingHours', function ($q) use ($currentDay, $currentTime) {
                $q->where('day_of_week', $currentDay)
                  ->where('opening_time', '<=', $currentTime)
                  ->where('closing_time', '>=', $currentTime);
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $restaurants
        ]);
    }

    public function toggleFavorite(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $user = $request->user();

        $favorite = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_id', $restaurant->id)
            ->where('favoritable_type', Restaurant::class)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $isFavorite = false;
        } else {
            UserFavorite::create([
                'user_id' => $user->id,
                'favoritable_id' => $restaurant->id,
                'favoritable_type' => Restaurant::class
            ]);
            $isFavorite = true;
        }

        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorite,
            'message' => $isFavorite ? 'Added to favorites' : 'Removed from favorites'
        ]);
    }

    public function favorites(Request $request)
    {
        $user = $request->user();

        $favoriteIds = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_type', Restaurant::class)
            ->pluck('favoritable_id');

        $restaurants = Restaurant::with(['images', 'features'])
            ->whereIn('id', $favoriteIds)
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $restaurants->items(),
            'pagination' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total()
            ]
        ]);
    }
}
