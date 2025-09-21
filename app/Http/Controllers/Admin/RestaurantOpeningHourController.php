<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantOpeningHour;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantOpeningHourController extends Controller
{
    public function index()
    {
        $hours = RestaurantOpeningHour::with('restaurant')->paginate(10);
        return view('admin.restaurant-opening-hours.index', compact('hours'));
    }

    public function create()
    {
        $restaurants = Restaurant::all();
        return view('admin.restaurant-opening-hours.create', compact('restaurants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'day' => 'required|string|max:255',
            'day_ar' => 'required|string|max:255',
            'opening_time' => 'required|string',
            'closing_time' => 'required|string'
        ]);

        RestaurantOpeningHour::create($validated);

        return redirect()->route('admin.restaurant-opening-hours.index')
            ->with('success', 'تم إضافة ساعات العمل بنجاح');
    }

    public function show(RestaurantOpeningHour $restaurantOpeningHour)
    {
        return view('admin.restaurant-opening-hours.show', compact('restaurantOpeningHour'));
    }

    public function edit(RestaurantOpeningHour $restaurantOpeningHour)
    {
        $restaurants = Restaurant::all();
        return view('admin.restaurant-opening-hours.edit', compact('restaurantOpeningHour', 'restaurants'));
    }

    public function update(Request $request, RestaurantOpeningHour $restaurantOpeningHour)
    {
        $validated = $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'day' => 'required|string|max:255',
            'day_ar' => 'required|string|max:255',
            'opening_time' => 'required|string',
            'closing_time' => 'required|string'
        ]);

        $restaurantOpeningHour->update($validated);

        return redirect()->route('admin.restaurant-opening-hours.index')
            ->with('success', 'تم تحديث ساعات العمل بنجاح');
    }

    public function destroy(RestaurantOpeningHour $restaurantOpeningHour)
    {
        $restaurantOpeningHour->delete();

        return redirect()->route('admin.restaurant-opening-hours.index')
            ->with('success', 'تم حذف ساعات العمل بنجاح');
    }
}