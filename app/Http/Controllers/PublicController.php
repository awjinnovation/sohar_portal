<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Restaurant;
use App\Models\Location;
use App\Models\Category;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function index()
    {
        $upcomingEvents = Event::active()
            ->upcoming()
            ->with('category')
            ->take(6)
            ->get();

        $featuredEvents = Event::active()
            ->featured()
            ->with('category')
            ->take(3)
            ->get();

        $restaurants = Restaurant::active()
            ->featured()
            ->take(6)
            ->get();

        $categories = Category::where('is_active', true)->get();

        $stats = [
            'total_events' => Event::active()->count(),
            'total_restaurants' => Restaurant::active()->count(),
            'emergency_locations' => Location::where('type', 'emergency')->where('is_active', true)->count(),
            'service_locations' => Location::whereIn('type', ['service', 'parking', 'restroom'])->where('is_active', true)->count(),
        ];

        return view('public.home', compact(
            'upcomingEvents',
            'featuredEvents',
            'restaurants',
            'categories',
            'stats'
        ));
    }

    public function events(Request $request)
    {
        $query = Event::active()->with('category');

        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('title_ar', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $events = $query->paginate(12);
        $categories = Category::where('is_active', true)->get();

        return view('public.events', compact('events', 'categories'));
    }

    public function eventDetails($id)
    {
        $event = Event::active()->with(['category', 'ticketPricing'])->findOrFail($id);

        // Parse pricing from JSON if stored in pricing field (pricing is already cast to array in model)
        $pricing = $event->pricing ?: [];

        $relatedEvents = Event::active()
            ->where('category_id', $event->category_id)
            ->where('id', '!=', $id)
            ->take(4)
            ->get();

        return view('public.event-details', compact('event', 'pricing', 'relatedEvents'));
    }

    public function restaurants(Request $request)
    {
        $query = Restaurant::active();

        if ($request->has('cuisine')) {
            $query->where('cuisine', $request->cuisine);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('name_ar', 'like', '%' . $search . '%')
                  ->orWhere('cuisine', 'like', '%' . $search . '%');
            });
        }

        $restaurants = $query->paginate(12);

        return view('public.restaurants', compact('restaurants'));
    }

    public function restaurantDetails($id)
    {
        $restaurant = Restaurant::active()->findOrFail($id);

        // JSON fields are already cast to arrays in model
        $features = $restaurant->features ?: [];
        $images = $restaurant->images ?: [];
        $openingHours = $restaurant->opening_hours ?: [];

        return view('public.restaurant-details', compact('restaurant', 'features', 'images', 'openingHours'));
    }

    public function locations(Request $request)
    {
        $query = Location::active();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $locations = $query->get()->groupBy('type');

        return view('public.locations', compact('locations'));
    }
}