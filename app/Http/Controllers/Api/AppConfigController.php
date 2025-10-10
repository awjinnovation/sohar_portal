<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    /**
     * Get all public app configuration settings for Flutter app
     */
    public function index(): JsonResponse
    {
        try {
            $config = AppSetting::getPublicSettings();

            return response()->json([
                'success' => true,
                'data' => $config,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch app configuration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get specific configuration value by key
     */
    public function show(string $key): JsonResponse
    {
        try {
            $value = AppSetting::get($key);

            if ($value === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration key not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch configuration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get theme configuration
     */
    public function theme(): JsonResponse
    {
        try {
            $themeConfig = [
                'primary_color' => AppSetting::get('primary_color', '#4A90E2'),
                'primary_dark_color' => AppSetting::get('primary_dark_color', '#3A7BC8'),
                'primary_light_color' => AppSetting::get('primary_light_color', '#6BA3E8'),
                'accent_color' => AppSetting::get('accent_color', '#FFA726'),
                'accent_dark_color' => AppSetting::get('accent_dark_color', '#FF9800'),
                'accent_light_color' => AppSetting::get('accent_light_color', '#FFB74D'),
                'background_color' => AppSetting::get('background_color', '#F8F9FA'),
                'surface_color' => AppSetting::get('surface_color', '#FFFFFF'),
                'error_color' => AppSetting::get('error_color', '#F44336'),
                'success_color' => AppSetting::get('success_color', '#4CAF50'),
                'warning_color' => AppSetting::get('warning_color', '#FF9800'),
                'text_primary_color' => AppSetting::get('text_primary_color', '#1E293B'),
                'text_secondary_color' => AppSetting::get('text_secondary_color', '#64748B'),
                'text_disabled_color' => AppSetting::get('text_disabled_color', '#CBD5E1'),
                'default_theme_mode' => AppSetting::get('default_theme_mode', 'light'),
                'enable_dark_mode' => AppSetting::get('enable_dark_mode', true),
            ];

            return response()->json([
                'success' => true,
                'data' => $themeConfig,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch theme configuration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get branding configuration
     */
    public function branding(): JsonResponse
    {
        try {
            $brandingConfig = [
                'app_name' => AppSetting::get('app_name', 'مهرجان صحار'),
                'app_name_en' => AppSetting::get('app_name_en', 'Sohar Festival'),
                'app_tagline' => AppSetting::get('app_tagline'),
                'app_tagline_en' => AppSetting::get('app_tagline_en'),
                'app_logo_url' => AppSetting::get('app_logo_url'),
                'app_icon_url' => AppSetting::get('app_icon_url'),
                'splash_image_url' => AppSetting::get('splash_image_url'),
                'loading_text' => AppSetting::get('loading_text', 'جاري التحميل...'),
                'loading_text_en' => AppSetting::get('loading_text_en', 'Loading...'),
                'splash_duration_ms' => AppSetting::get('splash_duration_ms', 2000),
            ];

            return response()->json([
                'success' => true,
                'data' => $brandingConfig,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branding configuration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get features configuration
     */
    public function features(): JsonResponse
    {
        try {
            $featuresConfig = [
                'enable_notifications' => AppSetting::get('enable_notifications', true),
                'enable_favorites' => AppSetting::get('enable_favorites', true),
                'enable_sharing' => AppSetting::get('enable_sharing', true),
                'enable_tickets' => AppSetting::get('enable_tickets', true),
                'enable_maps' => AppSetting::get('enable_maps', true),
                'enable_search' => AppSetting::get('enable_search', true),
                'enable_filters' => AppSetting::get('enable_filters', true),
                'show_bottom_nav' => AppSetting::get('show_bottom_nav', true),
                'enable_drawer_menu' => AppSetting::get('enable_drawer_menu', true),
                'enable_animations' => AppSetting::get('enable_animations', true),
                'enable_haptic_feedback' => AppSetting::get('enable_haptic_feedback', true),
            ];

            return response()->json([
                'success' => true,
                'data' => $featuresConfig,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch features configuration',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Check app version and maintenance mode
     */
    public function status(): JsonResponse
    {
        try {
            $statusConfig = [
                'maintenance_mode' => AppSetting::get('maintenance_mode', false),
                'maintenance_message' => AppSetting::get('maintenance_message'),
                'maintenance_message_en' => AppSetting::get('maintenance_message_en'),
                'app_version' => AppSetting::get('app_version', '1.0.0'),
                'min_supported_version' => AppSetting::get('min_supported_version', '1.0.0'),
                'force_update' => AppSetting::get('force_update', false),
            ];

            return response()->json([
                'success' => true,
                'data' => $statusConfig,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch app status',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
