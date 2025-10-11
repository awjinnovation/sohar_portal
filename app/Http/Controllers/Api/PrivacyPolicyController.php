<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use Illuminate\Http\Request;

class PrivacyPolicyController extends Controller
{
    public function index(Request $request)
    {
        $language = $request->get('language', 'en');

        $policy = PrivacyPolicy::active()
            ->byLanguage($language)
            ->first();

        if (!$policy) {
            return response()->json([
                'success' => false,
                'message' => 'Privacy policy not found for the requested language.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $policy,
        ]);
    }

    public function show($language = 'en')
    {
        $policy = PrivacyPolicy::active()
            ->byLanguage($language)
            ->first();

        if (!$policy) {
            return response()->json([
                'success' => false,
                'message' => 'Privacy policy not found for the requested language.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $policy,
        ]);
    }
}
