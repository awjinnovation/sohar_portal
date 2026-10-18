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
    public function run(): void
    {
        $tenant = app(TenantContext::class)->get();
        $tz = \App\Support\TenantTime::zone($tenant);

        PlatformUsersSeeder::user('admin@demo-events.example', 'Demo Events Admin')->syncRoles(['admin']);
        $cashier = PlatformUsersSeeder::user('vendor@demo-events.example', 'Demo Cashier', '96890000003');
        $cashier->syncRoles(['vendor-staff']);
        PlatformUsersSeeder::user('staff@demo-events.example', 'Demo Gate Staff', '96890000004')->syncRoles(['staff']);

        // Idempotent (natural keys): re-running db:seed never duplicates.
        $shows = Category::updateOrCreate(['name' => 'Shows'], [
            'name_ar' => 'العروض', 'description' => 'Live shows and performances',
            'description_ar' => 'عروض حية', 'icon_name' => 'theater_comedy', 'color_value' => 0xFF6D28D9,
            'display_order' => 1, 'is_active' => true,
        ]);
        $food = Category::updateOrCreate(['name' => 'Food & Market'], [
            'name_ar' => 'الطعام والسوق', 'description' => 'Street food and makers market',
            'description_ar' => 'طعام الشارع وسوق الحرفيين', 'icon_name' => 'restaurant', 'color_value' => 0xFF4338CA,
            'display_order' => 2, 'is_active' => true,
        ]);

        $events = [
            ['Opening Night Concert', 'حفل ليلة الافتتاح', $shows, 1, 2, '5.000', 500, 1000, 'wristband', true],
            ['Expo Day Pass', 'تذكرة يوم المعرض', $food, 0, 3, '2.000', -1, 0, null, true],
            ['Magic & Illusions Show', 'عرض السحر والخدع', $shows, 2, 2, '3.500', 200, 0, null, false],
        ];
        foreach ($events as [$title, $titleAr, $cat, $startIn, $days, $price, $capacity, $deposit, $depositType, $featured]) {
            Event::firstOrCreate(['title' => $title], [
                'title_ar' => $titleAr,
                'description' => "$title at Demo Events.", 'description_ar' => "$titleAr في فعاليات تجريبية.",
                'category_id' => $cat->id,
                // tenant-local wall-clock times
                'start_time' => now($tz)->addDays($startIn)->setTime(10, 0),
                'end_time' => now($tz)->addDays($startIn + $days)->setTime(23, 0),
                'location' => 'Exhibition Centre, Hall A', 'location_ar' => 'مركز المعارض، القاعة أ',
                'image_url' => 'https://picsum.photos/seed/'.md5($title).'/800/600',
                'price' => $price, 'currency' => 'OMR',
                'total_tickets' => $capacity, 'available_tickets' => $capacity,
                'organizer_name' => 'Demo Events', 'organizer_name_ar' => 'فعاليات تجريبية',
                'is_featured' => $featured, 'is_active' => true,
                'deposit_minor' => $deposit, 'deposit_type' => $depositType,
            ]);
        }

        Announcement::firstOrCreate(['title' => 'Welcome to Demo Events'], [
            'title_ar' => 'مرحباً بكم في فعاليات تجريبية',
            'content' => 'Top up your wallet and skip the queues!', 'content_ar' => 'اشحن محفظتك وتجنب الطوابير!',
            'type' => 'info', 'priority' => 1, 'is_pinned' => true, 'is_active' => true,
            'start_datetime' => now(), 'end_datetime' => now()->addDays(30),
            'created_by' => User::where('email', 'admin@demo-events.example')->value('id'),
        ]);

        $vendor = NorthBatinahVendorsSeeder::vendor(['en' => 'Expo Food Court', 'ar' => 'ساحة طعام المعرض'], 'food');
        NorthBatinahVendorsSeeder::vendor(['en' => 'Makers Market', 'ar' => 'سوق الحرفيين'], 'merchandise');
        $vendor->staff()->syncWithoutDetaching([$cashier->id => ['role' => 'manager']]);

        TermsAndCondition::firstOrCreate(['title' => 'Terms & Conditions'], [
            'title_ar' => 'الشروط والأحكام',
            'content' => '<h2>Demo Events Terms</h2><p>Wallet balances are refundable within 14 days. Promotional credit is not refundable.</p>',
            'content_ar' => '<h2>شروط فعاليات تجريبية</h2><p>أرصدة المحفظة قابلة للاسترداد خلال 14 يوماً. الرصيد الترويجي غير قابل للاسترداد.</p>',
            'is_active' => true,
        ]);
        PrivacyPolicy::firstOrCreate(['title' => 'Privacy Policy'], [
            'title_ar' => 'سياسة الخصوصية',
            'content' => '<h2>Demo Events Privacy</h2><p>We only use your phone number to sign you in and send ticket updates.</p>',
            'content_ar' => '<h2>خصوصية فعاليات تجريبية</h2><p>نستخدم رقم هاتفك فقط لتسجيل الدخول وإرسال تحديثات التذاكر.</p>',
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
