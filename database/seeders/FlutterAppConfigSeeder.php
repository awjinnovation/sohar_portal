<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class FlutterAppConfigSeeder extends Seeder
{
    /**
     * Seed comprehensive Flutter app configuration settings
     */
    public function run(): void
    {
        // App Identity
        AppSetting::set(
            'app_name',
            'مهرجان صحار',
            'string',
            'Application name displayed in Flutter app',
            true
        );

        AppSetting::set(
            'app_name_en',
            'Sohar Festival',
            'string',
            'Application name in English',
            true
        );

        AppSetting::set(
            'app_tagline',
            'اكتشف الفعاليات والمهرجانات في صحار',
            'string',
            'App tagline in Arabic',
            true
        );

        AppSetting::set(
            'app_tagline_en',
            'Discover Events and Festivals in Sohar',
            'string',
            'App tagline in English',
            true
        );

        // Logo & Branding
        AppSetting::set(
            'app_logo_url',
            asset('images/logo.png'),
            'string',
            'Main app logo URL',
            true
        );

        AppSetting::set(
            'app_icon_url',
            asset('images/icon.png'),
            'string',
            'App icon URL for splash screen',
            true
        );

        AppSetting::set(
            'splash_image_url',
            asset('images/splash.jpg'),
            'string',
            'Splash screen background image',
            true
        );

        // Loading & Splash Screen
        AppSetting::set(
            'loading_text',
            'جاري التحميل...',
            'string',
            'Loading text in Arabic',
            true
        );

        AppSetting::set(
            'loading_text_en',
            'Loading...',
            'string',
            'Loading text in English',
            true
        );

        AppSetting::set(
            'splash_duration_ms',
            2000,
            'integer',
            'Splash screen duration in milliseconds',
            true
        );

        // Theme Colors (Primary)
        AppSetting::set(
            'primary_color',
            '#4A90E2',
            'string',
            'Primary theme color (hex)',
            true
        );

        AppSetting::set(
            'primary_dark_color',
            '#3A7BC8',
            'string',
            'Primary dark theme color (hex)',
            true
        );

        AppSetting::set(
            'primary_light_color',
            '#6BA3E8',
            'string',
            'Primary light theme color (hex)',
            true
        );

        // Theme Colors (Accent)
        AppSetting::set(
            'accent_color',
            '#FFA726',
            'string',
            'Accent theme color (hex)',
            true
        );

        AppSetting::set(
            'accent_dark_color',
            '#FF9800',
            'string',
            'Accent dark theme color (hex)',
            true
        );

        AppSetting::set(
            'accent_light_color',
            '#FFB74D',
            'string',
            'Accent light theme color (hex)',
            true
        );

        // Theme Colors (Additional)
        AppSetting::set(
            'background_color',
            '#F8F9FA',
            'string',
            'Background color (hex)',
            true
        );

        AppSetting::set(
            'surface_color',
            '#FFFFFF',
            'string',
            'Surface color (hex)',
            true
        );

        AppSetting::set(
            'error_color',
            '#F44336',
            'string',
            'Error color (hex)',
            true
        );

        AppSetting::set(
            'success_color',
            '#4CAF50',
            'string',
            'Success color (hex)',
            true
        );

        AppSetting::set(
            'warning_color',
            '#FF9800',
            'string',
            'Warning color (hex)',
            true
        );

        // Text Colors
        AppSetting::set(
            'text_primary_color',
            '#1E293B',
            'string',
            'Primary text color (hex)',
            true
        );

        AppSetting::set(
            'text_secondary_color',
            '#64748B',
            'string',
            'Secondary text color (hex)',
            true
        );

        AppSetting::set(
            'text_disabled_color',
            '#CBD5E1',
            'string',
            'Disabled text color (hex)',
            true
        );

        // Font Configuration
        AppSetting::set(
            'font_family_ar',
            'Cairo',
            'string',
            'Arabic font family',
            true
        );

        AppSetting::set(
            'font_family_en',
            'Roboto',
            'string',
            'English font family',
            true
        );

        AppSetting::set(
            'font_size_small',
            12,
            'integer',
            'Small font size',
            true
        );

        AppSetting::set(
            'font_size_normal',
            14,
            'integer',
            'Normal font size',
            true
        );

        AppSetting::set(
            'font_size_large',
            18,
            'integer',
            'Large font size',
            true
        );

        AppSetting::set(
            'font_size_xlarge',
            24,
            'integer',
            'Extra large font size',
            true
        );

        // Theme Mode
        AppSetting::set(
            'default_theme_mode',
            'light',
            'string',
            'Default theme mode: light, dark, or system',
            true
        );

        AppSetting::set(
            'enable_dark_mode',
            true,
            'boolean',
            'Enable dark mode option',
            true
        );

        // Navigation & UI
        AppSetting::set(
            'show_bottom_nav',
            true,
            'boolean',
            'Show bottom navigation bar',
            true
        );

        AppSetting::set(
            'bottom_nav_items',
            json_encode([
                ['icon' => 'home', 'label' => 'الرئيسية', 'label_en' => 'Home'],
                ['icon' => 'calendar', 'label' => 'الفعاليات', 'label_en' => 'Events'],
                ['icon' => 'restaurant', 'label' => 'المطاعم', 'label_en' => 'Restaurants'],
                ['icon' => 'person', 'label' => 'حسابي', 'label_en' => 'Profile'],
            ]),
            'json',
            'Bottom navigation items configuration',
            true
        );

        AppSetting::set(
            'enable_drawer_menu',
            true,
            'boolean',
            'Enable drawer menu',
            true
        );

        // App Features
        AppSetting::set(
            'enable_notifications',
            true,
            'boolean',
            'Enable push notifications',
            true
        );

        AppSetting::set(
            'enable_favorites',
            true,
            'boolean',
            'Enable favorites feature',
            true
        );

        AppSetting::set(
            'enable_sharing',
            true,
            'boolean',
            'Enable social sharing',
            true
        );

        AppSetting::set(
            'enable_tickets',
            true,
            'boolean',
            'Enable ticket booking',
            true
        );

        AppSetting::set(
            'enable_maps',
            true,
            'boolean',
            'Enable Google Maps integration',
            true
        );

        AppSetting::set(
            'enable_search',
            true,
            'boolean',
            'Enable search functionality',
            true
        );

        AppSetting::set(
            'enable_filters',
            true,
            'boolean',
            'Enable category/date filters',
            true
        );

        // API Configuration
        AppSetting::set(
            'api_timeout_seconds',
            30,
            'integer',
            'API request timeout in seconds',
            true
        );

        AppSetting::set(
            'cache_duration_minutes',
            15,
            'integer',
            'Data cache duration in minutes',
            true
        );

        AppSetting::set(
            'pagination_limit',
            20,
            'integer',
            'Items per page for pagination',
            true
        );

        // Map Configuration
        AppSetting::set(
            'default_map_latitude',
            24.3589,
            'decimal',
            'Default map center latitude (Sohar)',
            true
        );

        AppSetting::set(
            'default_map_longitude',
            56.7085,
            'decimal',
            'Default map center longitude (Sohar)',
            true
        );

        AppSetting::set(
            'default_map_zoom',
            13,
            'integer',
            'Default map zoom level',
            true
        );

        // Contact & Support
        AppSetting::set(
            'support_email',
            'support@soharfestival.om',
            'string',
            'Support email address',
            true
        );

        AppSetting::set(
            'support_phone',
            '+968 2345 6789',
            'string',
            'Support phone number',
            true
        );

        AppSetting::set(
            'facebook_url',
            'https://facebook.com/soharfestival',
            'string',
            'Facebook page URL',
            true
        );

        AppSetting::set(
            'instagram_url',
            'https://instagram.com/soharfestival',
            'string',
            'Instagram page URL',
            true
        );

        AppSetting::set(
            'twitter_url',
            'https://twitter.com/soharfestival',
            'string',
            'Twitter page URL',
            true
        );

        // Legal & Privacy
        AppSetting::set(
            'terms_url',
            url('/terms'),
            'string',
            'Terms & Conditions URL',
            true
        );

        AppSetting::set(
            'privacy_url',
            url('/privacy'),
            'string',
            'Privacy Policy URL',
            true
        );

        // App Version
        AppSetting::set(
            'app_version',
            '1.0.0',
            'string',
            'Current app version',
            true
        );

        AppSetting::set(
            'min_supported_version',
            '1.0.0',
            'string',
            'Minimum supported app version',
            true
        );

        AppSetting::set(
            'force_update',
            false,
            'boolean',
            'Force users to update app',
            true
        );

        // Maintenance Mode
        AppSetting::set(
            'maintenance_mode',
            false,
            'boolean',
            'Enable maintenance mode',
            true
        );

        AppSetting::set(
            'maintenance_message',
            'التطبيق قيد الصيانة. نعتذر عن الإزعاج.',
            'string',
            'Maintenance mode message in Arabic',
            true
        );

        AppSetting::set(
            'maintenance_message_en',
            'App is under maintenance. Sorry for the inconvenience.',
            'string',
            'Maintenance mode message in English',
            true
        );

        // Currency
        AppSetting::set(
            'default_currency',
            'OMR',
            'string',
            'Default currency code',
            true
        );

        AppSetting::set(
            'currency_symbol',
            'ر.ع.',
            'string',
            'Currency symbol',
            true
        );

        // Language
        AppSetting::set(
            'default_language',
            'ar',
            'string',
            'Default language: ar or en',
            true
        );

        AppSetting::set(
            'supported_languages',
            json_encode(['ar', 'en']),
            'json',
            'Supported language codes',
            true
        );

        // Animation & Effects
        AppSetting::set(
            'enable_animations',
            true,
            'boolean',
            'Enable UI animations',
            true
        );

        AppSetting::set(
            'animation_duration_ms',
            300,
            'integer',
            'Default animation duration in milliseconds',
            true
        );

        AppSetting::set(
            'enable_haptic_feedback',
            true,
            'boolean',
            'Enable haptic feedback',
            true
        );

        // Image Configuration
        AppSetting::set(
            'image_quality',
            80,
            'integer',
            'Image compression quality (0-100)',
            true
        );

        AppSetting::set(
            'thumbnail_size',
            200,
            'integer',
            'Thumbnail image size in pixels',
            true
        );

        AppSetting::set(
            'max_image_size_mb',
            5,
            'integer',
            'Maximum image upload size in MB',
            true
        );

        echo "✅ Flutter app configuration settings seeded successfully!\n";
    }
}
