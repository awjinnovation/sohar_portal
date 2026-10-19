<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TermsAndCondition;
use Illuminate\Http\Request;

class TermsAndConditionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $terms = TermsAndCondition::latest()->paginate(10);
        return view('admin.terms-and-conditions.index', compact('terms'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.terms-and-conditions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'content' => 'required|string',
            'content_ar' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        TermsAndCondition::create($validated);

        return redirect()->route('admin.terms-and-conditions.index')
            ->with('success', 'أُضيفت الشروط والأحكام');
    }

    /**
     * Display the specified resource.
     */
    public function show(TermsAndCondition $termsAndCondition)
    {
        return view('admin.terms-and-conditions.show', compact('termsAndCondition'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TermsAndCondition $termsAndCondition)
    {
        return view('admin.terms-and-conditions.edit', compact('termsAndCondition'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TermsAndCondition $termsAndCondition)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'content' => 'required|string',
            'content_ar' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $termsAndCondition->update($validated);

        return redirect()->route('admin.terms-and-conditions.index')
            ->with('success', 'حُدِّثت الشروط والأحكام');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TermsAndCondition $termsAndCondition)
    {
        $termsAndCondition->delete();

        return redirect()->route('admin.terms-and-conditions.index')
            ->with('success', 'حُذفت الشروط والأحكام');
    }
}
