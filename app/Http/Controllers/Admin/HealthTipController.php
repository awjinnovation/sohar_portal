<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HealthTip;
use Illuminate\Http\Request;

class HealthTipController extends Controller
{
    public function index()
    {
        $tips = HealthTip::paginate(10);
        return view('admin.health-tips.index', compact('tips'));
    }

    public function create()
    {
        return view('admin.health-tips.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'category' => 'nullable|string|max:255',
            'category_ar' => 'nullable|string|max:255'
        ]);

        HealthTip::create($validated);

        return redirect()->route('admin.health-tips.index')
            ->with('success', 'تم إضافة النصيحة الصحية بنجاح');
    }

    public function show(HealthTip $healthTip)
    {
        return view('admin.health-tips.show', compact('healthTip'));
    }

    public function edit(HealthTip $healthTip)
    {
        return view('admin.health-tips.edit', compact('healthTip'));
    }

    public function update(Request $request, HealthTip $healthTip)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'category' => 'nullable|string|max:255',
            'category_ar' => 'nullable|string|max:255'
        ]);

        $healthTip->update($validated);

        return redirect()->route('admin.health-tips.index')
            ->with('success', 'تم تحديث النصيحة الصحية بنجاح');
    }

    public function destroy(HealthTip $healthTip)
    {
        $healthTip->delete();

        return redirect()->route('admin.health-tips.index')
            ->with('success', 'تم حذف النصيحة الصحية بنجاح');
    }
}