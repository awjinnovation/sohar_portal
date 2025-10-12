<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index()
    {
        $restaurants = Restaurant::latest()->paginate(10);
        return view("admin.restaurants.index", compact("restaurants"));
    }

    public function create()
    {
        return view("admin.restaurants.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|max:255",
            "name_ar" => "required|max:255",
            "description" => "required",
            "description_ar" => "required",
            "cuisine" => "required|max:100",
            "cuisine_ar" => "required|max:100",
            "location" => "required|max:255",
            "location_ar" => "required|max:255",
            "latitude" => "nullable|numeric|between:-90,90",
            "longitude" => "nullable|numeric|between:-180,180",
            "price_range" => "required|in:$,$$,$$$,$$$$",
            "image_url" => "nullable|url|max:500",
            "phone" => "nullable|max:20",
            "website" => "nullable|url|max:255",
            "is_open" => "boolean",
            "is_featured" => "boolean",
            "is_active" => "boolean"
        ]);

        Restaurant::create($validated);

        return redirect()->route("admin.restaurants.index")
            ->with("success", "تم إضافة المطعم بنجاح");
    }

    public function show(Restaurant $restaurant)
    {
        // Load media instead of relationships
        $restaurant->load('media');
        return view("admin.restaurants.show", compact("restaurant"));
    }

    public function edit(Restaurant $restaurant)
    {
        return view("admin.restaurants.edit", compact("restaurant"));
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        $validated = $request->validate([
            "name" => "required|max:255",
            "name_ar" => "required|max:255",
            "description" => "required",
            "description_ar" => "required",
            "cuisine" => "required|max:100",
            "cuisine_ar" => "required|max:100",
            "location" => "required|max:255",
            "location_ar" => "required|max:255",
            "latitude" => "nullable|numeric|between:-90,90",
            "longitude" => "nullable|numeric|between:-180,180",
            "price_range" => "required|in:$,$$,$$$,$$$$",
            "image_url" => "nullable|url|max:500",
            "phone" => "nullable|max:20",
            "website" => "nullable|url|max:255",
            "is_open" => "boolean",
            "is_featured" => "boolean",
            "is_active" => "boolean"
        ]);

        $restaurant->update($validated);

        return redirect()->route("admin.restaurants.index")
            ->with("success", "تم تحديث المطعم بنجاح");
    }

    public function destroy(Restaurant $restaurant)
    {
        $restaurant->delete();
        return redirect()->route("admin.restaurants.index")
            ->with("success", "تم حذف المطعم بنجاح");
    }
}