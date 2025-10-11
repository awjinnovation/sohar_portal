<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use Illuminate\Http\Request;

class PrivacyPolicyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $policies = PrivacyPolicy::latest()->paginate(10);
        return view('admin.privacy-policies.index', compact('policies'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.privacy-policies.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'language' => 'required|string|max:10',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        PrivacyPolicy::create($validated);

        return redirect()->route('admin.privacy-policies.index')
            ->with('success', 'Privacy policy created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PrivacyPolicy $privacyPolicy)
    {
        return view('admin.privacy-policies.show', compact('privacyPolicy'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PrivacyPolicy $privacyPolicy)
    {
        return view('admin.privacy-policies.edit', compact('privacyPolicy'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PrivacyPolicy $privacyPolicy)
    {
        $validated = $request->validate([
            'language' => 'required|string|max:10',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $privacyPolicy->update($validated);

        return redirect()->route('admin.privacy-policies.index')
            ->with('success', 'Privacy policy updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PrivacyPolicy $privacyPolicy)
    {
        $privacyPolicy->delete();

        return redirect()->route('admin.privacy-policies.index')
            ->with('success', 'Privacy policy deleted successfully.');
    }
}
