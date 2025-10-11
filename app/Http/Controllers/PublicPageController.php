<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrivacyPolicy;
use App\Models\TermsAndCondition;

class PublicPageController extends Controller
{
    public function privacyPolicy()
    {
        $policy = PrivacyPolicy::where('is_active', true)->latest()->first();

        if (!$policy) {
            abort(404, 'Privacy Policy not found');
        }

        return view('public.privacy-policy', compact('policy'));
    }

    public function termsAndConditions()
    {
        $terms = TermsAndCondition::where('is_active', true)->latest()->first();

        if (!$terms) {
            abort(404, 'Terms and Conditions not found');
        }

        return view('public.terms-and-conditions', compact('terms'));
    }
}
