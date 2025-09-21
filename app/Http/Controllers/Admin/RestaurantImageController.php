<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantImage;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantImageController extends Controller
{
    public function index()
    {
        $images = RestaurantImage::with('restaurant')->paginate(10);
        return view('admin.restaurant-images.index', compact('images'));
    }

    public function create()
    {
        $restaurants = Restaurant::all();
        return view('admin.restaurant-images.create', compact('restaurants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'image_url' => 'required|string|max:255',
            'display_order' => 'nullable|integer'
        ]);

        RestaurantImage::create($validated);

        return redirect()->route('admin.restaurant-images.index')
            ->with('success', 'تم إضافة صورة المطعم بنجاح');
    }

    public function show(RestaurantImage $restaurantImage)
    {
        return view('admin.restaurant-images.show', compact('restaurantImage'));
    }

    public function edit(RestaurantImage $restaurantImage)
    {
        $restaurants = Restaurant::all();
        return view('admin.restaurant-images.edit', compact('restaurantImage', 'restaurants'));
    }

    public function update(Request $request, RestaurantImage $restaurantImage)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'image_url' => 'required|string|max:255',
            'display_order' => 'nullable|integer'
        ]);

        $restaurantImage->update($validated);

        return redirect()->route('admin.restaurant-images.index')
            ->with('success', 'تم تحديث صورة المطعم بنجاح');
    }

    public function destroy(RestaurantImage $restaurantImage)
    {
        $restaurantImage->delete();

        return redirect()->route('admin.restaurant-images.index')
            ->with('success', 'تم حذف صورة المطعم بنجاح');
    }
}