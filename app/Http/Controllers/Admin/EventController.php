<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Category;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->get('view', 'list'); // list or calendar

        if ($view === 'calendar') {
            // Get all events for calendar view
            $events = Event::with('category')
                ->where('is_active', true)
                ->orderBy('start_time')
                ->get();

            // Format events for FullCalendar
            $calendarEvents = $events->map(function($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title_ar,
                    'start' => $event->start_time->toIso8601String(),
                    'end' => $event->end_time->toIso8601String(),
                    'backgroundColor' => $event->is_featured ? '#FFA726' : '#4A90E2',
                    'borderColor' => $event->is_featured ? '#FF9800' : '#3A7BC8',
                    'extendedProps' => [
                        'location' => $event->location_ar,
                        'category' => $event->category->name_ar ?? '',
                        'available_tickets' => $event->available_tickets,
                        'total_tickets' => $event->total_tickets,
                        'price' => $event->price . ' ' . $event->currency,
                    ]
                ];
            });

            return view('admin.events.index', compact('events', 'calendarEvents', 'view'));
        }

        // List view with pagination
        $events = Event::with('category')
            ->latest('start_time')
            ->paginate(10);

        return view('admin.events.index', compact('events', 'view'));
    }

    public function create()
    {
        $categories = Category::active()->ordered()->get();
        $mapLocations = \App\Models\MapLocation::where('is_active', true)->get();
        return view('admin.events.create', compact('categories', 'mapLocations'));
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
            'map_location_id' => 'nullable|exists:map_locations,id',
            'image_url' => 'nullable|url|max:500',
            'images' => 'nullable|json',
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

        // Parse images JSON if provided
        if (isset($validated['images'])) {
            $validated['images'] = json_decode($validated['images'], true);
        }

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
        $mapLocations = \App\Models\MapLocation::where('is_active', true)->get();
        return view('admin.events.edit', compact('event', 'categories', 'mapLocations'));
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
            'map_location_id' => 'nullable|exists:map_locations,id',
            'image_url' => 'nullable|url|max:500',
            'images' => 'nullable|json',
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

        // Parse images JSON if provided
        if (isset($validated['images'])) {
            $validated['images'] = json_decode($validated['images'], true);
        }

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