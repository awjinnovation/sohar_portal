<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::with('user')->paginate(10);
        return view('admin.notifications.index', compact('notifications'));
    }

    public function create()
    {
        $users = User::all();
        return view('admin.notifications.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'message' => 'required|string',
            'message_ar' => 'required|string',
            'type' => 'nullable|string|max:255',
            'is_read' => 'boolean'
        ]);

        $validated['is_read'] = $validated['is_read'] ?? false;

        Notification::create($validated);

        return redirect()->route('admin.notifications.index')
            ->with('success', 'تم إضافة الإشعار بنجاح');
    }

    public function show(Notification $notification)
    {
        return view('admin.notifications.show', compact('notification'));
    }

    public function edit(Notification $notification)
    {
        $users = User::all();
        return view('admin.notifications.edit', compact('notification', 'users'));
    }

    public function update(Request $request, Notification $notification)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'message' => 'required|string',
            'message_ar' => 'required|string',
            'type' => 'nullable|string|max:255',
            'is_read' => 'boolean'
        ]);

        $validated['is_read'] = $validated['is_read'] ?? false;

        $notification->update($validated);

        return redirect()->route('admin.notifications.index')
            ->with('success', 'تم تحديث الإشعار بنجاح');
    }

    public function destroy(Notification $notification)
    {
        $notification->delete();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'تم حذف الإشعار بنجاح');
    }
}