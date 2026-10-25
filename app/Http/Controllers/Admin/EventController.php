<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\MapLocation;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use TenantValidation;

    public function index(Request $request)
    {
        $view = $request->get('view', 'list'); // list or calendar

        if ($view === 'calendar') {
            // Get all events for calendar view
            $events = Event::with('category')
                ->where('is_active', true)
                ->orderBy('start_time')
                ->get();

            // Format events for FullCalendar (tenant brand colors)
            $primary = $this->currentTenant()?->setting('theme.colors.primary') ?: '#4A90E2';
            $secondary = $this->currentTenant()?->setting('theme.colors.secondary') ?: '#FFA726';
            $calendarEvents = $events->map(function($event) use ($primary, $secondary) {
                return [
                    'id' => $event->id,
                    'title' => $event->title_ar,
                    'start' => \App\Support\TenantTime::iso($event->start_time),
                    'end' => \App\Support\TenantTime::iso($event->end_time),
                    'backgroundColor' => $event->is_featured ? $secondary : $primary,
                    'borderColor' => $event->is_featured ? $secondary : $primary,
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
        $mapLocations = MapLocation::where('is_active', true)->get();
        return view('admin.events.create', compact('categories', 'mapLocations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['currency'] = $validated['currency'] ?? $this->currentTenant()?->currency() ?? 'OMR';

        // Parse images JSON if provided
        if (isset($validated['images'])) {
            $validated['images'] = json_decode($validated['images'], true);
        }

        Event::create($validated);

        return redirect()->route('admin.events.index')
            ->with('success', 'أُضيفت الفعالية');
    }

    public function show(Event $event)
    {
        $event->load(['category', 'tags', 'tickets' => function($query) {
            $query->latest()->limit(10);
        }]);
        
        // Per-type sales summary (docs/ticket_types_contract.md §3).
        $ticketTypes = app(EventTicketTypeController::class)->list($event);

        return view('admin.events.show', compact('event', 'ticketTypes'));
    }

    public function edit(Event $event)
    {
        $categories = Category::active()->ordered()->get();
        $mapLocations = MapLocation::where('is_active', true)->get();
        return view('admin.events.edit', compact('event', 'categories', 'mapLocations'));
    }

    public function update(Request $request, Event $event)
    {
        // Prices live on the ticket types; a posted price updates the default (General) type.
        $validated = $request->validate(['price' => 'nullable|numeric|min:0'] + $this->rules());
        if (array_key_exists('price', $validated) && $validated['price'] === null) {
            unset($validated['price']);
        }
        if (isset($validated['price']) && ($default = $event->defaultTicketType())) {
            $default->update(['price_minor' => $this->currentTenant()?->toMinor((string) $validated['price']) ?? 0]);
        }
        $validated['currency'] = $validated['currency'] ?? $this->currentTenant()?->currency() ?? 'OMR';

        // Parse images JSON if provided
        if (isset($validated['images'])) {
            $validated['images'] = json_decode($validated['images'], true);
        }

        $event->update($validated);

        return redirect()->route('admin.events.index')
            ->with('success', 'حُدِّثت الفعالية');
    }

    public function destroy(Event $event)
    {
        if ($event->tickets()->count() > 0) {
            return redirect()->route('admin.events.index')
                ->with('error', 'لا يمكن حذف الفعالية لوجود تذاكر مرتبطة بها');
        }

        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('success', 'حُذفت الفعالية');
    }

    private function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'title_ar' => 'required|max:255',
            'description' => 'required',
            'description_ar' => 'required',
            'category_id' => ['required', $this->tenantExists('categories')],
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'required|max:255',
            'location_ar' => 'nullable|max:255',
            'map_location_id' => ['nullable', $this->tenantExists('map_locations')],
            'image_url' => 'nullable|url|max:500',
            'images' => 'nullable|json',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|in:OMR,USD,EUR',
            'available_tickets' => 'nullable|integer|min:0',
            'total_tickets' => 'nullable|integer|min:0',
            'organizer_name' => 'nullable|max:255',
            'organizer_name_ar' => 'nullable|max:255',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
