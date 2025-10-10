<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index(Request $request)
    {
        $query = Category::withCount('events');

        // Filter active categories
        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        // Sort by name or event count
        $sortBy = $request->input('sort_by', 'name');
        if ($sortBy === 'events_count') {
            $query->orderByDesc('events_count');
        } else {
            $query->orderBy('name');
        }

        $categories = $query->get();

        return response()->json([
            'success' => true,
            'data' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'name_ar' => $category->name_ar,
                    'description' => $category->description,
                    'description_ar' => $category->description_ar,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'events_count' => $category->events_count,
                    'is_active' => $category->is_active,
                ];
            }),
            'message' => 'Categories retrieved successfully'
        ]);
    }

    /**
     * Get single category with its events
     */
    public function show($id)
    {
        $category = Category::with(['events' => function ($query) {
            $query->where('is_active', true)
                  ->orderBy('start_time');
        }])->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
                'name_ar' => $category->name_ar,
                'description' => $category->description,
                'description_ar' => $category->description_ar,
                'icon' => $category->icon,
                'color' => $category->color,
                'events_count' => $category->events->count(),
                'events' => $category->events->map(function ($event) {
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
                }),
            ],
            'message' => 'Category retrieved successfully'
        ]);
    }
}
