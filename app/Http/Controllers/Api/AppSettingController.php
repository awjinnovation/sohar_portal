<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/app-settings[/{key}] — the tenant's public settings only.
 */
class AppSettingController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(): JsonResponse
    {
        $t = $this->context->get();

        $data = array_merge([
            'app_name' => $t->localizedName('en'),
            'app_name_ar' => $t->localizedName('ar'),
            'app_version' => $t->setting('app.latest_version'),
            'maintenance_mode' => (bool) $t->setting('app.maintenance_mode'),
            'contact_email' => $t->setting('tenant.support.email'),
            'contact_phone' => $t->setting('tenant.support.phone'),
            'social_media' => array_filter((array) $t->setting('tenant.social', [])),
            'terms_url' => $t->setting('tenant.legal.terms_url'),
            'privacy_url' => $t->setting('tenant.legal.privacy_url'),
            'about_url' => $t->setting('tenant.support.website'),
        ], AppSetting::getPublicSettings());

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show(string $key): JsonResponse
    {
        $public = AppSetting::getPublicSettings();

        if (! array_key_exists($key, $public)) {
            return response()->json(['success' => false, 'message' => 'Setting not found'], 404);
        }

        return response()->json(['success' => true, 'data' => ['key' => $key, 'value' => $public[$key]]]);
    }
}
