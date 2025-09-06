<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventTag;
use Illuminate\Http\Request;

class EventTagController extends Controller
{
    public function index()
    {
        $tags = EventTag::withCount('events')->latest()->paginate(20);
        return view('admin.event-tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.event-tags.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:50|unique:event_tags',
            'tag_name_ar' => 'required|string|max:50',
            'color_hex' => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        EventTag::create($validated);

        return redirect()->route('admin.event-tags.index')
            ->with('success', 'تم إضافة الوسم بنجاح');
    }

    public function show(EventTag $eventTag)
    {
        $eventTag->load('events');
        return view('admin.event-tags.show', compact('eventTag'));
    }

    public function edit(EventTag $eventTag)
    {
        return view('admin.event-tags.edit', compact('eventTag'));
    }

    public function update(Request $request, EventTag $eventTag)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:50|unique:event_tags,tag_name,' . $eventTag->id,
            'tag_name_ar' => 'required|string|max:50',
            'color_hex' => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        $eventTag->update($validated);

        return redirect()->route('admin.event-tags.index')
            ->with('success', 'تم تحديث الوسم بنجاح');
    }

    public function destroy(EventTag $eventTag)
    {
        $eventTag->delete();
        return redirect()->route('admin.event-tags.index')
            ->with('success', 'تم حذف الوسم بنجاح');
    }
}
