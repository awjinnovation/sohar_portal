<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\TenantValidation;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTag;
use Illuminate\Http\Request;

/**
 * Tags attached to an event (event_tags: event_id, tag, tag_ar).
 */
class EventTagController extends Controller
{
    use TenantValidation;

    public function index(Request $request)
    {
        $tags = EventTag::with('event')
            ->when($request->filled('event_id'), fn ($q) => $q->where('event_id', $request->integer('event_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.event-tags.index', compact('tags'));
    }

    public function create(Request $request)
    {
        $events = $this->events();
        $selectedEventId = $request->integer('event_id') ?: null;

        return view('admin.event-tags.create', compact('events', 'selectedEventId'));
    }

    public function store(Request $request)
    {
        EventTag::create($this->validated($request));

        return redirect()->route('admin.event-tags.index')
            ->with('success', 'أُضيف الوسم');
    }

    public function show(EventTag $eventTag)
    {
        $eventTag->load('event');

        return view('admin.event-tags.show', compact('eventTag'));
    }

    public function edit(EventTag $eventTag)
    {
        $events = $this->events();

        return view('admin.event-tags.edit', compact('eventTag', 'events'));
    }

    public function update(Request $request, EventTag $eventTag)
    {
        $eventTag->update($this->validated($request, $eventTag));

        return redirect()->route('admin.event-tags.index')
            ->with('success', 'حُدِّث الوسم');
    }

    public function destroy(EventTag $eventTag)
    {
        $eventTag->delete();

        return redirect()->route('admin.event-tags.index')
            ->with('success', 'حُذف الوسم');
    }

    private function events()
    {
        return Event::orderByDesc('start_time')->get(['id', 'title', 'title_ar', 'start_time']);
    }

    private function validated(Request $request, ?EventTag $ignore = null): array
    {
        $unique = $this->tenantUnique('event_tags', 'tag')
            ->where('event_id', (int) $request->input('event_id'));
        if ($ignore) {
            $unique->ignore($ignore->id);
        }

        return $request->validate([
            'event_id' => ['required', 'integer', $this->tenantExists('events')],
            'tag' => ['required', 'string', 'max:50', $unique],
            'tag_ar' => ['required', 'string', 'max:50'],
        ]);
    }
}
