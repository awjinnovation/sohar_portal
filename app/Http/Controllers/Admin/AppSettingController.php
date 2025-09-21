<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function index()
    {
        $settings = AppSetting::paginate(10);
        return view('admin.app-settings.index', compact('settings'));
    }

    public function create()
    {
        return view('admin.app-settings.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:app_settings,key',
            'value' => 'required|string',
            'description' => 'nullable|string'
        ]);

        AppSetting::create($validated);

        return redirect()->route('admin.app-settings.index')
            ->with('success', 'تم إضافة الإعداد بنجاح');
    }

    public function show(AppSetting $appSetting)
    {
        return view('admin.app-settings.show', compact('appSetting'));
    }

    public function edit(AppSetting $appSetting)
    {
        return view('admin.app-settings.edit', compact('appSetting'));
    }

    public function update(Request $request, AppSetting $appSetting)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:app_settings,key,' . $appSetting->id,
            'value' => 'required|string',
            'description' => 'nullable|string'
        ]);

        $appSetting->update($validated);

        return redirect()->route('admin.app-settings.index')
            ->with('success', 'تم تحديث الإعداد بنجاح');
    }

    public function destroy(AppSetting $appSetting)
    {
        $appSetting->delete();

        return redirect()->route('admin.app-settings.index')
            ->with('success', 'تم حذف الإعداد بنجاح');
    }
}