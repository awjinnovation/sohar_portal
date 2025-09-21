<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantFeature;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantFeatureController extends Controller
{
    public function index()
    {
        $features = RestaurantFeature::with('restaurant')->paginate(10);
        return view('admin.restaurant-features.index', compact('features'));
    }

    public function create()
    {
        $restaurants = Restaurant::all();
        return view('admin.restaurant-features.create', compact('restaurants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'feature' => 'required|string|max:255',
            'feature_ar' => 'required|string|max:255'
        ]);

        RestaurantFeature::create($validated);

        return redirect()->route('admin.restaurant-features.index')
            ->with('success', 'تم إضافة ميزة المطعم بنجاح');
    }

    public function show(RestaurantFeature $restaurantFeature)
    {
        return view('admin.restaurant-features.show', compact('restaurantFeature'));
    }

    public function edit(RestaurantFeature $restaurantFeature)
    {
        $restaurants = Restaurant::all();
        return view('admin.restaurant-features.edit', compact('restaurantFeature', 'restaurants'));
    }

    public function update(Request $request, RestaurantFeature $restaurantFeature)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'feature' => 'required|string|max:255',
            'feature_ar' => 'required|string|max:255'
        ]);

        $restaurantFeature->update($validated);

        return redirect()->route('admin.restaurant-features.index')
            ->with('success', 'تم تحديث ميزة المطعم بنجاح');
    }

    public function destroy(RestaurantFeature $restaurantFeature)
    {
        $restaurantFeature->delete();

        return redirect()->route('admin.restaurant-features.index')
            ->with('success', 'تم حذف ميزة المطعم بنجاح');
    }
}