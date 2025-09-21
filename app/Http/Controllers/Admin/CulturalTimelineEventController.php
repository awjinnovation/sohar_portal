<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CulturalTimelineEvent;
use Illuminate\Http\Request;

class CulturalTimelineEventController extends Controller
{
    public function index()
    {
        $events = CulturalTimelineEvent::paginate(10);
        return view('admin.cultural-timeline-events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.cultural-timeline-events.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'year' => 'required|integer',
            'era' => 'nullable|string|max:255',
            'era_ar' => 'nullable|string|max:255'
        ]);

        CulturalTimelineEvent::create($validated);

        return redirect()->route('admin.cultural-timeline-events.index')
            ->with('success', 'تم إضافة الحدث الثقافي بنجاح');
    }

    public function show(CulturalTimelineEvent $culturalTimelineEvent)
    {
        return view('admin.cultural-timeline-events.show', compact('culturalTimelineEvent'));
    }

    public function edit(CulturalTimelineEvent $culturalTimelineEvent)
    {
        return view('admin.cultural-timeline-events.edit', compact('culturalTimelineEvent'));
    }

    public function update(Request $request, CulturalTimelineEvent $culturalTimelineEvent)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'year' => 'required|integer',
            'era' => 'nullable|string|max:255',
            'era_ar' => 'nullable|string|max:255'
        ]);

        $culturalTimelineEvent->update($validated);

        return redirect()->route('admin.cultural-timeline-events.index')
            ->with('success', 'تم تحديث الحدث الثقافي بنجاح');
    }

    public function destroy(CulturalTimelineEvent $culturalTimelineEvent)
    {
        $culturalTimelineEvent->delete();

        return redirect()->route('admin.cultural-timeline-events.index')
            ->with('success', 'تم حذف الحدث الثقافي بنجاح');
    }
}