<?php

namespace App\Http\Controllers;

use App\Models\PrivacyPolicy;
use App\Models\Tenant;
use App\Models\TermsAndCondition;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Public legal pages: /privacy-policy?tenant=<slug> (default tenant if omitted).
 */
class PublicPageController extends Controller
{
    public function __construct(private TenantContext $context)
    {
    }

    private function resolveTenant(Request $request): void
    {
        if ($slug = $request->query('tenant')) {
            $tenant = Tenant::findBySlug($slug);
            abort_unless($tenant && $tenant->isActive(), 404);
            $this->context->set($tenant);
        }
    }

    public function privacyPolicy(Request $request)
    {
        $this->resolveTenant($request);
        $policy = PrivacyPolicy::where('is_active', true)->latest()->first();
        abort_unless($policy, 404, 'Privacy Policy not found');

        return view('public.privacy-policy', compact('policy'));
    }

    public function termsAndConditions(Request $request)
    {
        $this->resolveTenant($request);
        $terms = TermsAndCondition::where('is_active', true)->latest()->first();
        abort_unless($terms, 404, 'Terms and Conditions not found');

        return view('public.terms-and-conditions', compact('terms'));
    }
}
