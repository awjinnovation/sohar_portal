<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermsAndCondition;
use Illuminate\Http\Request;

class TermsAndConditionController extends Controller
{
    public function index(Request $request)
    {
        $language = $request->get('language', 'en');
        $type = $request->get('type', 'general');

        $terms = TermsAndCondition::active()
            ->byLanguage($language)
            ->byType($type)
            ->first();

        if (!$terms) {
            return response()->json([
                'success' => false,
                'message' => 'Terms and conditions not found for the requested language and type.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $terms,
        ]);
    }

    public function show($type = 'general', $language = 'en')
    {
        $terms = TermsAndCondition::active()
            ->byLanguage($language)
            ->byType($type)
            ->first();

        if (!$terms) {
            return response()->json([
                'success' => false,
                'message' => 'Terms and conditions not found for the requested language and type.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $terms,
        ]);
    }
}
