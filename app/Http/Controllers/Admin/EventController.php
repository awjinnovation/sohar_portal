<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Category;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with('category')
            ->latest('start_time')
            ->paginate(10);
        
        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        $categories = Category::active()->ordered()->get();
        return view('admin.events.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'title_ar' => 'required|max:255',
            'description' => 'required',
            'description_ar' => 'required',
            'category_id' => 'required|exists:categories,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'required|max:255',
            'location_ar' => 'nullable|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'image_url' => 'nullable|url|max:500',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|in:OMR,USD,EUR',
            'available_tickets' => 'nullable|integer|min:0',
            'total_tickets' => 'nullable|integer|min:0',
            'organizer_name' => 'nullable|max:255',
            'organizer_name_ar' => 'nullable|max:255',
            'is_featured' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $validated['currency'] = $validated['currency'] ?? 'OMR';
        
        Event::create($validated);

        return redirect()->route('admin.events.index')
            ->with('success', 'تم إضافة الفعالية بنجاح');
    }

    public function show(Event $event)
    {
        $event->load(['category', 'tags', 'tickets' => function($query) {
            $query->latest()->limit(10);
        }]);
        
        return view('admin.events.show', compact('event'));
    }

    public function edit(Event $event)
    {
        $categories = Category::active()->ordered()->get();
        return view('admin.events.edit', compact('event', 'categories'));
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'title_ar' => 'required|max:255',
            'description' => 'required',
            'description_ar' => 'required',
            'category_id' => 'required|exists:categories,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'required|max:255',
            'location_ar' => 'nullable|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'image_url' => 'nullable|url|max:500',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|in:OMR,USD,EUR',
            'available_tickets' => 'nullable|integer|min:0',
            'total_tickets' => 'nullable|integer|min:0',
            'organizer_name' => 'nullable|max:255',
            'organizer_name_ar' => 'nullable|max:255',
            'is_featured' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $validated['currency'] = $validated['currency'] ?? 'OMR';
        
        $event->update($validated);

        return redirect()->route('admin.events.index')
            ->with('success', 'تم تحديث الفعالية بنجاح');
    }

    public function destroy(Event $event)
    {
        if ($event->tickets()->count() > 0) {
            return redirect()->route('admin.events.index')
                ->with('error', 'لا يمكن حذف الفعالية لوجود تذاكر مرتبطة بها');
        }

        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('success', 'تم حذف الفعالية بنجاح');
    }
}