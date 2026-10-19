<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Event;
use App\Models\PrivacyPolicy;
use App\Models\TermsAndCondition;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Tenant `demo` ("Demo Events"): English-first, indigo/purple theme, map
 * module off, points on — proves the platform is configurable per tenant.
 */
class DemoTenantSeeder extends Seeder
{
    /**
     * Seed content of tenant `demo`. Also read by `content:sync-arabic`, which
     * matches rows by their English natural key (name / title).
     */
    public static function content(): array
    {
        return [
            'categories' => [
                ['name' => 'Shows', 'name_ar' => 'العروض', 'description' => 'Live shows and performances',
                    'description_ar' => 'عروض حية', 'icon_name' => 'theater_comedy', 'color_value' => 0xFF6D28D9, 'display_order' => 1],
                ['name' => 'Food & Market', 'name_ar' => 'الطعام والسوق', 'description' => 'Street food and makers market',
                    'description_ar' => 'أكلات الشارع وسوق الحرفيين', 'icon_name' => 'restaurant', 'color_value' => 0xFF4338CA, 'display_order' => 2],
            ],
            // [title, title_ar, category, start in days, days, price, capacity, deposit, deposit type, featured]
            'events' => [
                ['Opening Night Concert', 'حفل ليلة الافتتاح', 'Shows', 1, 2, '5.000', 500, 1000, 'wristband', true],
                ['Expo Day Pass', 'تذكرة يوم المعرض', 'Food & Market', 0, 3, '2.000', -1, 0, null, true],
                ['Magic & Illusions Show', 'عرض السحر والخدع', 'Shows', 2, 2, '3.500', 200, 0, null, false],
            ],
            'event_text' => [
                'description' => fn (string $title) => "$title at Demo Events.",
                'description_ar' => fn (string $titleAr) => "$titleAr ضمن فعاليات تجريبية.",
                'location' => 'Exhibition Centre, Hall A', 'location_ar' => 'مركز المعارض، القاعة أ',
                'organizer_name' => 'Demo Events', 'organizer_name_ar' => 'فعاليات تجريبية',
            ],
            'announcements' => [
                ['title' => 'Welcome to Demo Events', 'title_ar' => 'مرحبًا بكم في فعاليات تجريبية',
                    'content' => 'Top up your wallet and skip the queues!', 'content_ar' => 'اشحن محفظتك وتجنب الطوابير.'],
            ],
            'vendors' => [
                ['en' => 'Expo Food Court', 'ar' => 'ساحة طعام المعرض', 'category' => 'food'],
                ['en' => 'Makers Market', 'ar' => 'سوق الحرفيين', 'category' => 'merchandise'],
            ],
            'terms' => [
                'title' => 'Terms & Conditions', 'title_ar' => 'الشروط والأحكام',
                'content' => '<h2>Demo Events Terms</h2><p>Wallet balances are refundable within 14 days. Promotional credit is not refundable.</p>',
                'content_ar' => '<h2>شروط فعاليات تجريبية</h2><p>يمكن استرداد رصيد المحفظة خلال 14 يومًا، أما الرصيد الترويجي فلا يُسترد.</p>',
            ],
            'privacy' => [
                'title' => 'Privacy Policy', 'title_ar' => 'سياسة الخصوصية',
                'content' => '<h2>Demo Events Privacy</h2><p>We only use your phone number to sign you in and send ticket updates.</p>',
                'content_ar' => '<h2>سياسة الخصوصية في فعاليات تجريبية</h2><p>لا نستخدم رقم هاتفك إلا لتسجيل دخولك وإرسال مستجدات تذاكرك.</p>',
            ],
        ];
    }

    public function run(): void
    {
        $tenant = app(TenantContext::class)->get();
        $tz = \App\Support\TenantTime::zone($tenant);
        $content = self::content();

        PlatformUsersSeeder::user('admin@demo-events.example', 'Demo Events Admin')->syncRoles(['admin']);
        $cashier = PlatformUsersSeeder::user('vendor@demo-events.example', 'Demo Cashier', '96890000003');
        $cashier->syncRoles(['vendor-staff']);
        PlatformUsersSeeder::user('staff@demo-events.example', 'Demo Gate Staff', '96890000004')->syncRoles(['staff']);

        // Idempotent (natural keys): re-running db:seed never duplicates.
        $categories = [];
        foreach ($content['categories'] as $c) {
            $categories[$c['name']] = Category::updateOrCreate(['name' => $c['name']], [
                'name_ar' => $c['name_ar'], 'description' => $c['description'],
                'description_ar' => $c['description_ar'], 'icon_name' => $c['icon_name'], 'color_value' => $c['color_value'],
                'display_order' => $c['display_order'], 'is_active' => true,
            ]);
        }

        $text = $content['event_text'];
        foreach ($content['events'] as [$title, $titleAr, $cat, $startIn, $days, $price, $capacity, $deposit, $depositType, $featured]) {
            Event::firstOrCreate(['title' => $title], [
                'title_ar' => $titleAr,
                'description' => $text['description']($title), 'description_ar' => $text['description_ar']($titleAr),
                'category_id' => $categories[$cat]->id,
                // tenant-local wall-clock times
                'start_time' => now($tz)->addDays($startIn)->setTime(10, 0),
                'end_time' => now($tz)->addDays($startIn + $days)->setTime(23, 0),
                'location' => $text['location'], 'location_ar' => $text['location_ar'],
                'image_url' => 'https://picsum.photos/seed/'.md5($title).'/800/600',
                'price' => $price, 'currency' => 'OMR',
                'total_tickets' => $capacity, 'available_tickets' => $capacity,
                'organizer_name' => $text['organizer_name'], 'organizer_name_ar' => $text['organizer_name_ar'],
                'is_featured' => $featured, 'is_active' => true,
                'deposit_minor' => $deposit, 'deposit_type' => $depositType,
            ]);
        }

        foreach ($content['announcements'] as $a) {
            Announcement::firstOrCreate(['title' => $a['title']], [
                'title_ar' => $a['title_ar'],
                'content' => $a['content'], 'content_ar' => $a['content_ar'],
                'type' => 'info', 'priority' => 1, 'is_pinned' => true, 'is_active' => true,
                'start_datetime' => now(), 'end_datetime' => now()->addDays(30),
                'created_by' => User::where('email', 'admin@demo-events.example')->value('id'),
            ]);
        }

        $vendors = array_map(
            fn ($v) => NorthBatinahVendorsSeeder::vendor(['en' => $v['en'], 'ar' => $v['ar']], $v['category']),
            $content['vendors'],
        );
        $vendors[0]->staff()->syncWithoutDetaching([$cashier->id => ['role' => 'manager']]);

        TermsAndCondition::firstOrCreate(['title' => $content['terms']['title']], [
            'title_ar' => $content['terms']['title_ar'],
            'content' => $content['terms']['content'],
            'content_ar' => $content['terms']['content_ar'],
            'is_active' => true,
        ]);
        PrivacyPolicy::firstOrCreate(['title' => $content['privacy']['title']], [
            'title_ar' => $content['privacy']['title_ar'],
            'content' => $content['privacy']['content'],
            'content_ar' => $content['privacy']['content_ar'],
            'is_active' => true,
        ]);

        $visitor = User::where('phone_number', '96891234567')->first();
        if ($visitor) {
            $wallets = app(WalletService::class);
            $wallet = $wallets->walletFor($visitor, $tenant);
            $wallets->credit($wallet, 20000, 'topup', ['idempotency_key' => 'seed:topup', 'meta' => ['kind' => 'seed']]);
        }
    }
}
