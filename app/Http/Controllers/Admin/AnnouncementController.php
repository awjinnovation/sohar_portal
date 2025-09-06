<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with("creator")->latest()->paginate(10);
        return view("admin.announcements.index", compact("announcements"));
    }

    public function create()
    {
        return view("admin.announcements.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "title" => "required|max:255",
            "title_ar" => "required|max:255",
            "content" => "required",
            "content_ar" => "required",
            "type" => "required|in:info,warning,emergency,celebration",
            "priority" => "nullable|integer",
            "is_pinned" => "boolean",
            "is_active" => "boolean",
            "start_datetime" => "required|date",
            "end_datetime" => "nullable|date|after:start_datetime"
        ]);

        $validated["created_by"] = auth()->id();

        Announcement::create($validated);

        return redirect()->route("admin.announcements.index")
            ->with("success", "تم إضافة الإعلان بنجاح");
    }

    public function show(Announcement $announcement)
    {
        return view("admin.announcements.show", compact("announcement"));
    }

    public function edit(Announcement $announcement)
    {
        return view("admin.announcements.edit", compact("announcement"));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            "title" => "required|max:255",
            "title_ar" => "required|max:255",
            "content" => "required",
            "content_ar" => "required",
            "type" => "required|in:info,warning,emergency,celebration",
            "priority" => "nullable|integer",
            "is_pinned" => "boolean",
            "is_active" => "boolean",
            "start_datetime" => "required|date",
            "end_datetime" => "nullable|date|after:start_datetime"
        ]);

        $announcement->update($validated);

        return redirect()->route("admin.announcements.index")
            ->with("success", "تم تحديث الإعلان بنجاح");
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return redirect()->route("admin.announcements.index")
            ->with("success", "تم حذف الإعلان بنجاح");
    }
}