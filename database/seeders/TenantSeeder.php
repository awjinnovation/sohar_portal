<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Tenant definitions. Values mirror the Flutter app's bundled fallbacks
 * (sohar_north_app/assets/tenant/<slug>.json) so app and backend agree;
 * images are served from public/tenants/<slug>/. Legal URLs point at this
 * backend's public pages and keep the {lang} placeholder (contract §5).
 *
 * North Batinah content is seed data of tenant `north-batinah` only.
 */
class TenantSeeder extends Seeder
{
    public static function northBatinah(): array
    {
        $settings = [
            'tenant' => [
                'tagline' => [
                    'en' => 'Sohar Festival, races and heritage across the wilayats of North Al Batinah',
                    'ar' => 'مهرجان صحار والسباقات والتراث في ولايات شمال الباطنة',
                ],
                'default_locale' => 'ar',
                'locales' => [
                    'ar',
                    'en',
                ],
                'currency' => 'OMR',
                'currency_decimals' => 3,
                'timezone' => 'Asia/Muscat',
                'support' => [
                    // Placeholders: set the organizer's real contacts in the admin.
                    'email' => 'info@example.om',
                    'phone' => '',
                    'whatsapp' => '',
                    'website' => 'https://services.bng.gov.om:8989',
                ],
                'social' => [
                    'instagram' => '',
                    'x' => '',
                    'tiktok' => '',
                    'youtube' => '',
                ],
                'legal' => [
                    'terms_url' => 'https://services.bng.gov.om:8989/index.php/terms-and-conditions?lang={lang}',
                    'privacy_url' => 'https://services.bng.gov.om:8989/index.php/privacy-policy?lang={lang}',
                ],
                'organization' => [
                    'en' => 'North Batinah Governor Office',
                    'ar' => 'مكتب محافظ شمال الباطنة',
                ],
            ],
            'branding' => [
                'logo_url' => '/tenants/north-batinah/logo.png',
                'logo_on_primary_url' => '/tenants/north-batinah/logo_on_primary.png',
                'icon_url' => '/tenants/north-batinah/mark.png',
                'splash_url' => '',
                'onboarding' => [
                    [
                        'image_url' => '/tenants/north-batinah/onboarding_1.jpg',
                        'title' => [
                            'en' => 'Welcome to North Batinah Events',
                            'ar' => 'مرحباً بكم في فعاليات شمال الباطنة',
                        ],
                        'body' => [
                            'en' => 'Sohar Festival, camel and horse racing, the rally and heritage events across Sohar, Shinas, Liwa, Saham, Al Khaburah and Al Suwaiq.',
                            'ar' => 'مهرجان صحار وسباقات الهجن والخيل والرالي والفعاليات التراثية في صحار وشناص ولوى وصحم والخابورة والسويق.',
                        ],
                    ],
                    [
                        'image_url' => '/tenants/north-batinah/onboarding_2.jpg',
                        'title' => [
                            'en' => 'Explore Events',
                            'ar' => 'استكشف الفعاليات',
                        ],
                        'body' => [
                            'en' => 'Browse through exciting events, add them to your favorites, and never miss what matters to you.',
                            'ar' => 'تصفح الفعاليات المثيرة، أضفها إلى المفضلة، ولن تفوتك أي فعالية تهمك.',
                        ],
                    ],
                    [
                        'image_url' => '/tenants/north-batinah/onboarding_3.jpg',
                        'title' => [
                            'en' => 'QR Code Access',
                            'ar' => 'الوصول برمز QR',
                        ],
                        'body' => [
                            'en' => 'Use QR codes for quick event check-ins, special offers, and exclusive content access.',
                            'ar' => 'استخدم رموز QR للدخول السريع للفعاليات والعروض الخاصة والمحتوى الحصري.',
                        ],
                    ],
                ],
            ],
            'theme' => [
                'mode' => 'light',
                'colors' => [
                    'primary' => '#2F8E80',
                    'on_primary' => '#FFFFFF',
                    'secondary' => '#E5883C',
                    'on_secondary' => '#FFFFFF',
                    'background' => '#FFFFFF',
                    'surface' => '#FFFFFF',
                    'on_surface' => '#1A1A1A',
                    'muted' => '#666666',
                    'outline' => '#E0E0E0',
                    'success' => '#4CAF50',
                    'warning' => '#FF9800',
                    'error' => '#F44336',
                    'wallet_card_start' => '#2F8E80',
                    'wallet_card_end' => '#206257',
                ],
                'font_family_ar' => 'Cairo',
                'font_family_en' => 'Cairo',
                'corner_radius' => 16,
                'density' => 'comfortable',
            ],
            'modules' => [
                'events' => true,
                'tickets' => true,
                'calendar' => true,
                'map' => true,
                'vendors' => true,
                'announcements' => true,
                'favorites' => true,
                'notifications' => true,
                'wallet' => true,
                'topup' => true,
                'deposits' => true,
                'points' => true,
                'refunds' => true,
                'ticket_transfer' => false,
                'staff_scanner' => true,
            ],
            'navigation' => [
                'home',
                'events',
                'tickets',
                'map',
                'more',
            ],
            'map' => [
                // Sohar: between the festival grounds (Sohar Entertainment
                // Centre) and Sohar Fort / Corniche. Sources: docs/north_batinah_seed_sources.md
                'center' => [
                    'lat' => 24.3480,
                    'lng' => 56.7160,
                ],
                'zoom' => 13,
                'venue' => [
                    'en' => 'Sohar Entertainment Centre',
                    'ar' => 'مركز صحار الترفيهي',
                ],
            ],
            'wallet' => [
                'topup_presets_minor' => [
                    5000,
                    10000,
                    20000,
                    50000,
                ],
                'min_topup_minor' => 1000,
                'max_topup_minor' => 500000,
                'max_balance_minor' => 1000000,
                'refunds' => [
                    'enabled' => true,
                    'window_days' => 14,
                    'fee_minor' => 0,
                    'min_minor' => 1000,
                    'methods' => [
                        'original_payment',
                        'bank_transfer',
                    ],
                ],
                'topup_bonus' => [],
            ],
            'points' => [
                'label' => [
                    'en' => 'Points',
                    'ar' => 'نقاط',
                ],
                'earn_per_currency_unit' => 10,
                'redeem_value_minor_per_point' => 10,
                'min_redeem_points' => 100,
                'expiry_days' => 365,
                'earn_on_checkin' => false,
                'tiers' => [
                    [
                        'code' => 'bronze',
                        'name' => [
                            'en' => 'Bronze',
                            'ar' => 'برونزي',
                        ],
                        'min_points' => 0,
                        'multiplier' => 1.0,
                        'color' => '#B87333',
                    ],
                    [
                        'code' => 'silver',
                        'name' => [
                            'en' => 'Silver',
                            'ar' => 'فضي',
                        ],
                        'min_points' => 1000,
                        'multiplier' => 1.25,
                        'color' => '#9CA3AF',
                    ],
                    [
                        'code' => 'gold',
                        'name' => [
                            'en' => 'Gold',
                            'ar' => 'ذهبي',
                        ],
                        'min_points' => 5000,
                        'multiplier' => 1.5,
                        'color' => '#D4A017',
                    ],
                ],
            ],
            'app' => [
                'min_version' => '6.0.0',
                'latest_version' => '6.0.0',
                'force_update' => false,
                'maintenance_mode' => false,
                'store_urls' => [
                    'ios' => '',
                    'android' => '',
                ],
            ],
            'payments' => [
                'billing' => [
                    'address' => 'Sohar',
                    'city' => 'Sohar',
                    'state' => 'Al Batinah North',
                    'zip' => '311',
                    'country' => 'Oman',
                ],
            ],
            'legacy' => [
                'splash_duration_ms' => 2000,
                'loading_text' => 'جاري التحميل...',
                'loading_text_en' => 'Loading...',
                'currency_symbol' => 'ر.ع.',
                'pagination_limit' => 20,
            ],
        ];
        $settings['theme'] += [
            'header_style' => 'gradient',
            'header_gradient' => ['#2F8E80', '#2C7DA5'],
            'font_family_numeric' => 'Inter',
        ];
        $settings['home_blocks'] = ['wallet_card', 'quick_actions', 'announcements', 'featured_events', 'happening_today', 'map_teaser', 'categories'];
        $settings['quick_actions'] = ['topup', 'pay', 'tickets', 'map', 'points'];
        $settings['merchant'] = ['confirm_threshold_minor' => 20000];
        $settings['tenant']['legal'] = [
            'terms_url' => url('/terms-and-conditions').'?tenant=north-batinah&lang={lang}',
            'privacy_url' => url('/privacy-policy').'?tenant=north-batinah&lang={lang}',
        ];

        return [
            'slug' => 'north-batinah',
            'name' => [
                'en' => 'North Batinah Events',
                'ar' => 'فعاليات شمال الباطنة',
            ],
            'status' => 'active',
            'settings' => $settings,
        ];
    }

    public static function demo(): array
    {
        $settings = [
            'tenant' => [
                'tagline' => [
                    'en' => 'Concerts, markets and shows — all in one app',
                    'ar' => 'حفلات وأسواق وعروض في تطبيق واحد',
                ],
                'default_locale' => 'en',
                'locales' => [
                    'en',
                    'ar',
                ],
                'currency' => 'OMR',
                'currency_decimals' => 3,
                'timezone' => 'Asia/Muscat',
                'support' => [
                    'email' => 'support@demo-events.example',
                    'phone' => '+96890000000',
                    'whatsapp' => '+96890000000',
                    'website' => 'https://demo-events.example',
                ],
                'social' => [
                    'instagram' => 'https://instagram.com/demo.events',
                    'x' => 'https://x.com/demoevents',
                    'tiktok' => '',
                    'youtube' => '',
                ],
                'legal' => [
                    'terms_url' => 'https://demo-events.example/terms?lang={lang}',
                    'privacy_url' => 'https://demo-events.example/privacy?lang={lang}',
                ],
                'organization' => [
                    'en' => 'Demo Events LLC',
                    'ar' => 'شركة الفعاليات التجريبية',
                ],
            ],
            'branding' => [
                'logo_url' => '/tenants/demo/logo.png',
                'logo_on_primary_url' => '/tenants/demo/logo_on_primary.png',
                'icon_url' => '/tenants/demo/mark.png',
                'splash_url' => '',
                'onboarding' => [
                    [
                        'image_url' => '/tenants/demo/onboarding_1.jpg',
                        'title' => [
                            'en' => 'Welcome to Demo Events',
                            'ar' => 'مرحباً بك في فعاليات تجريبية',
                        ],
                        'body' => [
                            'en' => 'Find concerts, markets and shows happening near you.',
                            'ar' => 'اعثر على الحفلات والأسواق والعروض القريبة منك.',
                        ],
                    ],
                    [
                        'image_url' => '/tenants/demo/onboarding_2.jpg',
                        'title' => [
                            'en' => 'Tickets in your pocket',
                            'ar' => 'تذاكرك في جيبك',
                        ],
                        'body' => [
                            'en' => 'Book in seconds and keep every ticket in one place.',
                            'ar' => 'احجز في ثوانٍ واحتفظ بجميع تذاكرك في مكان واحد.',
                        ],
                    ],
                    [
                        'image_url' => '/tenants/demo/onboarding_3.jpg',
                        'title' => [
                            'en' => 'Pay with your wallet',
                            'ar' => 'ادفع بمحفظتك',
                        ],
                        'body' => [
                            'en' => 'Top up once, pay everywhere at the venue and earn points.',
                            'ar' => 'اشحن مرة واحدة وادفع في كل مكان واكسب النقاط.',
                        ],
                    ],
                ],
            ],
            'theme' => [
                'mode' => 'system',
                'colors' => [
                    'primary' => '#4F46E5',
                    'on_primary' => '#FFFFFF',
                    'secondary' => '#9333EA',
                    'on_secondary' => '#FFFFFF',
                    'background' => '#F7F7FB',
                    'surface' => '#FFFFFF',
                    'on_surface' => '#111827',
                    'muted' => '#6B7280',
                    'outline' => '#E5E7EB',
                    'success' => '#16A34A',
                    'warning' => '#F59E0B',
                    'error' => '#DC2626',
                    'wallet_card_start' => '#4F46E5',
                    'wallet_card_end' => '#9333EA',
                ],
                'font_family_ar' => 'IBM Plex Sans Arabic',
                'font_family_en' => 'Inter',
                'corner_radius' => 12,
                'density' => 'comfortable',
            ],
            'modules' => [
                'events' => true,
                'tickets' => true,
                'calendar' => true,
                'map' => false,
                'vendors' => true,
                'announcements' => true,
                'favorites' => true,
                'notifications' => true,
                'wallet' => true,
                'topup' => true,
                'deposits' => true,
                'points' => true,
                'refunds' => true,
                'ticket_transfer' => false,
                'staff_scanner' => false,
            ],
            'navigation' => [
                'home',
                'events',
                'wallet',
                'tickets',
                'map',
                'more',
            ],
            'map' => [
                'center' => [
                    'lat' => 23.588,
                    'lng' => 58.3829,
                ],
                'zoom' => 13,
                'venue' => [
                    'en' => 'Demo Arena',
                    'ar' => 'ساحة العروض',
                ],
            ],
            'wallet' => [
                'topup_presets_minor' => [
                    2000,
                    5000,
                    10000,
                    25000,
                ],
                'min_topup_minor' => 1000,
                'max_topup_minor' => 250000,
                'max_balance_minor' => 500000,
                'refunds' => [
                    'enabled' => true,
                    'window_days' => 7,
                    'fee_minor' => 250,
                    'min_minor' => 1000,
                    'methods' => [
                        'original_payment',
                    ],
                ],
                'topup_bonus' => [
                    [
                        'min_minor' => 10000,
                        'bonus_percent' => 5,
                    ],
                    [
                        'min_minor' => 25000,
                        'bonus_percent' => 10,
                    ],
                ],
            ],
            'points' => [
                'label' => [
                    'en' => 'Stars',
                    'ar' => 'نجوم',
                ],
                'earn_per_currency_unit' => 5,
                'redeem_value_minor_per_point' => 20,
                'min_redeem_points' => 50,
                'expiry_days' => 180,
                'earn_on_checkin' => true,
                'checkin_points' => 50,
                'tiers' => [
                    [
                        'code' => 'member',
                        'name' => [
                            'en' => 'Member',
                            'ar' => 'عضو',
                        ],
                        'min_points' => 0,
                        'multiplier' => 1.0,
                        'color' => '#6366F1',
                    ],
                    [
                        'code' => 'vip',
                        'name' => [
                            'en' => 'VIP',
                            'ar' => 'كبار الشخصيات',
                        ],
                        'min_points' => 2000,
                        'multiplier' => 2.0,
                        'color' => '#A855F7',
                    ],
                ],
            ],
            'app' => [
                'min_version' => '6.0.0',
                'latest_version' => '6.0.0',
                'force_update' => false,
                'maintenance_mode' => false,
                'store_urls' => [
                    'ios' => '',
                    'android' => '',
                ],
            ],
            'payments' => [
                'billing' => [
                    'address' => 'Exhibition Centre',
                    'city' => 'Muscat',
                    'state' => 'Muscat',
                    'zip' => '100',
                    'country' => 'Oman',
                ],
            ],
        ];
        $settings['theme'] += [
            'header_style' => 'gradient',
            'header_gradient' => ['#4F46E5', '#7C3AED'],
            'font_family_numeric' => 'Inter',
        ];
        $settings['home_blocks'] = ['wallet_card', 'quick_actions', 'points_progress', 'featured_events', 'happening_today', 'vendors', 'announcements'];
        $settings['quick_actions'] = ['topup', 'pay', 'points', 'tickets', 'vendors'];
        $settings['merchant'] = ['confirm_threshold_minor' => 10000];
        $settings['tenant']['legal'] = [
            'terms_url' => url('/terms-and-conditions').'?tenant=demo&lang={lang}',
            'privacy_url' => url('/privacy-policy').'?tenant=demo&lang={lang}',
        ];

        return [
            'slug' => 'demo',
            'name' => [
                'en' => 'Demo Events',
                'ar' => 'فعاليات تجريبية',
            ],
            'status' => 'active',
            'settings' => $settings,
        ];
    }

    public function run(): void
    {
        foreach ([self::northBatinah(), self::demo()] as $def) {
            $tenant = Tenant::firstOrNew(['slug' => $def['slug']]);
            $existing = $tenant->exists ? ($tenant->settings ?? []) : [];
            $tenant->fill($def);
            // Keep legacy keys folded from app_settings by the tenancy migration.
            $tenant->settings = \App\Support\Arr2::mergeSettings($existing, $def['settings']);
            $tenant->save();
        }
    }
}
