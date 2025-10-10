<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $type = $request->get('type');

        $settings = AppSetting::query()
            ->when($search, function($query, $search) {
                $query->where('setting_key', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($type, function($query, $type) {
                $query->where('setting_type', $type);
            })
            ->orderBy('setting_key')
            ->paginate(20);

        return view('admin.app-settings.index', compact('settings'));
    }

    public function create()
    {
        return view('admin.app-settings.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'setting_key' => 'required|string|max:100|unique:app_settings,setting_key',
            'setting_value' => 'required|string',
            'setting_type' => 'required|in:string,integer,boolean,json,decimal',
            'description' => 'nullable|string',
            'is_public' => 'boolean'
        ]);

        AppSetting::create($validated);

        // Clear cache
        AppSetting::clearCache();

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
            'setting_key' => 'required|string|max:100|unique:app_settings,setting_key,' . $appSetting->id,
            'setting_value' => 'required|string',
            'setting_type' => 'required|in:string,integer,boolean,json,decimal',
            'description' => 'nullable|string',
            'is_public' => 'boolean'
        ]);

        $appSetting->update($validated);

        // Clear cache
        AppSetting::clearCache();

        return redirect()->route('admin.app-settings.index')
            ->with('success', 'تم تحديث الإعداد بنجاح');
    }

    public function destroy(AppSetting $appSetting)
    {
        $appSetting->delete();

        // Clear cache
        AppSetting::clearCache();

        return redirect()->route('admin.app-settings.index')
            ->with('success', 'تم حذف الإعداد بنجاح');
    }
}