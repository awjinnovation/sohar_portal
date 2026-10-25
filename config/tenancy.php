<?php

/*
|--------------------------------------------------------------------------
| Multi-tenancy
|--------------------------------------------------------------------------
|
| Single database, every tenant-owned row carries `tenant_id` and is scoped
| by App\Tenancy\TenantScope. The API resolves the tenant from the X-Tenant
| header (falling back to `default`), the admin panel from the session.
|
| `defaults` is the full settings document of a tenant (see
| docs/platform_contract.md §5). A tenant's stored `settings` JSON is deep
| merged on top of it (associative arrays merge, lists are replaced), so a
| tenant only needs to store what differs from these defaults.
|
*/

return [

    'default' => env('TENANCY_DEFAULT_TENANT', 'north-batinah'),

    'header' => 'X-Tenant',

    // Seconds the computed tenant config document is cached.
    'config_cache_ttl' => (int) env('TENANCY_CONFIG_CACHE_TTL', 300),

    'defaults' => [
        'tenant' => [
            'tagline' => ['en' => '', 'ar' => ''],
            'default_locale' => 'ar',
            'locales' => ['ar', 'en'],
            'currency' => 'OMR',
            'currency_decimals' => 3,
            'timezone' => 'Asia/Muscat',
            'support' => ['email' => '', 'phone' => '', 'whatsapp' => '', 'website' => ''],
            'social' => ['instagram' => '', 'x' => '', 'tiktok' => '', 'youtube' => '', 'facebook' => ''],
            'legal' => ['terms_url' => '', 'privacy_url' => ''],
            // legal URLs may contain a {lang} placeholder (replaced by the app).
            // Organisation shown on public legal pages / footers (server only).
            'organization' => ['en' => '', 'ar' => ''],
        ],

        'branding' => [
            'logo_url' => '',
            'logo_on_primary_url' => '',
            'icon_url' => '',
            'splash_url' => '',
            'onboarding' => [],
            // Optional: true = show the logo on a white chip over coloured
            // headers, false = it works on the brand colour, null = auto
            // (chip when there is no separate logo_on_primary_url).
            'logo_needs_chip' => null,
        ],

        'theme' => [
            'mode' => 'system',
            'colors' => [
                'primary' => '#2F8E80', 'on_primary' => '#FFFFFF',
                'secondary' => '#E5883C', 'on_secondary' => '#FFFFFF',
                'background' => '#F7F8FA', 'surface' => '#FFFFFF', 'on_surface' => '#111827',
                'muted' => '#6B7280', 'outline' => '#E5E7EB',
                'success' => '#16A34A', 'warning' => '#F59E0B', 'error' => '#DC2626',
                'wallet_card_start' => '#2F8E80', 'wallet_card_end' => '#1F5F56',
            ],
            'dark_colors' => [],
            'font_family_ar' => 'Cairo',
            'font_family_en' => 'Inter',
            'corner_radius' => 16,
            'density' => 'comfortable',
            // §5a optional presentation keys
            'header_style' => 'gradient',          // gradient | solid | image
            'header_gradient' => [],               // ["#hex", "#hex"]
            'header_image_url' => null,
            'card_pattern_url' => null,
            'font_family_numeric' => 'Inter',
        ],

        'home_blocks' => ['wallet_card', 'quick_actions', 'announcements', 'featured_events', 'happening_today', 'points_progress', 'categories'],
        'quick_actions' => ['topup', 'pay', 'points', 'tickets', 'map'],
        'merchant' => ['confirm_threshold_minor' => 20000],
        // Sign-in phone field (app): dialing code without "+", local number
        // length and the placeholder shown in the field.
        'auth' => ['country_code' => '968', 'phone_length' => 8, 'phone_hint' => '9123 4567'],

        'modules' => [
            'events' => true, 'tickets' => true, 'calendar' => true, 'map' => true, 'vendors' => true,
            'announcements' => true, 'favorites' => true, 'notifications' => true,
            'wallet' => true, 'topup' => true, 'deposits' => true, 'points' => true, 'refunds' => true,
            'ticket_transfer' => false, 'staff_scanner' => true,
            // AI Experience (docs/ai_and_gate_contract.md §A2) — off unless enabled per tenant.
            'ai_assistant' => false, 'ai_plan' => false,
            // Places to visit + custom map, and content collections + pages
            // (docs/places_content_contract.md §1) — off unless enabled per tenant.
            'places' => false, 'content' => false,
        ],

        'navigation' => ['home', 'events', 'wallet', 'tickets', 'more'],

        // venue: optional label shown with the map card.
        'map' => ['center' => ['lat' => 24.3474, 'lng' => 56.7535], 'zoom' => 15, 'venue' => ['en' => '', 'ar' => '']],

        'wallet' => [
            'topup_presets_minor' => [5000, 10000, 20000, 50000],
            'min_topup_minor' => 1000,
            'max_topup_minor' => 500000,
            'max_balance_minor' => 1000000,
            // [{min_minor, bonus_percent}] — highest matching tier wins, credited as promo.
            'topup_bonus' => [],
            // Days a promo credit (points redemption, top-up bonus, goodwill)
            // stays spendable; null = never expires. Expired by wallet:expire-promo.
            'promo_expiry_days' => null,
            'refunds' => [
                'enabled' => true,
                'window_days' => 14,
                'fee_minor' => 0,
                'min_minor' => 1000,
                'methods' => ['original_payment', 'bank_transfer'],
            ],
        ],

        'points' => [
            'label' => ['en' => 'Points', 'ar' => 'نقاط'],
            'earn_per_currency_unit' => 10,
            'redeem_value_minor_per_point' => 10,
            'min_redeem_points' => 100,
            'expiry_days' => 365,
            'earn_on_checkin' => false,
            'checkin_points' => 0,
            'tiers' => [
                ['code' => 'bronze', 'name' => ['en' => 'Bronze', 'ar' => 'برونزي'], 'min_points' => 0, 'multiplier' => 1.0, 'color' => '#B87333'],
                ['code' => 'silver', 'name' => ['en' => 'Silver', 'ar' => 'فضي'], 'min_points' => 1000, 'multiplier' => 1.25, 'color' => '#9CA3AF'],
                ['code' => 'gold', 'name' => ['en' => 'Gold', 'ar' => 'ذهبي'], 'min_points' => 5000, 'multiplier' => 1.5, 'color' => '#D4A017'],
            ],
        ],

        'app' => [
            'min_version' => '1.0.0',
            'latest_version' => '1.0.0',
            'force_update' => false,
            'maintenance_mode' => false,
            'maintenance_message' => ['en' => '', 'ar' => ''],
            'store_urls' => ['ios' => '', 'android' => ''],
        ],

        // AI Experience (§A2). assistant_name / welcome / suggestions are
        // public (/tenant/config → ai); the rest is server-only.
        'ai' => [
            'assistant_name' => ['en' => 'Ask Mawsim', 'ar' => 'اسأل موسم'],
            'welcome' => [
                'en' => 'Hi! Ask me about events, tickets, parking or your wallet.',
                'ar' => 'أهلًا وسهلًا، اسألني عن الفعاليات والتذاكر والمواقف أو محفظتك.',
            ],
            'suggestions' => [
                ['en' => "What's on tonight?", 'ar' => 'ما فعاليات الليلة؟'],
                ['en' => 'Plan an evening for my family', 'ar' => 'خطّط أمسية لعائلتي'],
                ['en' => 'How do refunds work?', 'ar' => 'كيف أسترد رصيدي؟'],
            ],
            // Server-only:
            'monthly_token_budget' => 2000000,   // input + output tokens per calendar month (tenant time zone)
            'copilot_enabled' => true,           // admin copilot, daily briefing, gate insights
            // Free text the concierge may quote (opening hours, gates, parking, rules).
            'venue_info' => ['en' => '', 'ar' => ''],
        ],

        // Server-side only (never exposed by /tenant/config).
        'payments' => [
            'billing' => [
                'address' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country' => 'Oman',
            ],
        ],

        // Gate box office (docs/gate_sales_contract.md §1): gate staff sell
        // tickets and send the visitor a payment link. Read-only for the
        // gate app in GET /gate/session → settings.sales.
        'gate_sales' => [
            'enabled' => false,
            'link_ttl_minutes' => 30,     // lifetime of the payment link; the order holds its seats meanwhile
            'send_sms' => true,           // default of POST /gate/sales/orders send_sms
            'max_quantity' => 10,         // tickets per order
            'allow_future_dates' => false, // true = staff may sell for a later day of the event
        ],

        'messages' => [
            // Placeholders: :app (tenant name), :code (OTP), :minutes.
            'otp_sms' => [
                'en' => 'Your :app verification code is :code. Valid for :minutes minutes.',
                'ar' => 'رمز التحقق في :app هو :code، وصلاحيته :minutes دقائق.',
            ],
            // Gate box office payment link. Placeholders: :app, :tickets
            // (counted noun in the genitive, e.g. «تذكرتين» / "2 tickets"),
            // :event, :amount (OMR 1.000), :minutes (counted, e.g. «30 دقيقة»),
            // :url, :reference.
            'box_office_sms' => [
                'en' => ':app: order of :tickets for :event, total :amount. Pay within :minutes: :url',
                'ar' => ':app. طلب :tickets لفعالية «:event» بقيمة :amount. ادفع عبر الرابط خلال :minutes: :url',
            ],
        ],

        // Free-form key/value pairs still served by the legacy /config endpoints
        // (font sizes, animation flags, cache durations...).
        'legacy' => [],
    ],
];
