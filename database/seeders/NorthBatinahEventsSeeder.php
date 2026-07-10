<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Category;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\Announcement;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\Ticket;
use App\Models\Payment;

/**
 * General North Batinah Events platform seed data.
 *
 * This is a regional events catalogue — concerts, sports, workshops, forums,
 * exhibitions and community activities across the wilayats of North Batinah
 * (Sohar, Saham, Liwa, Shinas, Al Suwaiq, Al Khaburah). The "Sohar Festival"
 * is just one registerable event among many, not the whole app.
 *
 * All content is bilingual (ar/en). Dates are anchored around "now" so the
 * app's Today / Upcoming / Featured sections and the registration/booking flow
 * populate. Availability derives from events.total_tickets (per-day capacity).
 */
class NorthBatinahEventsSeeder extends Seeder
{
    private function img(string $seed, int $w = 800, int $h = 600): string
    {
        return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
    }

    public function run(): void
    {
        $now = Carbon::now();

        // ------------------------------------------------------------------
        // Categories (general event types)
        // ------------------------------------------------------------------
        $categoryDefs = [
            ['key' => 'festivals',   'name' => 'Festivals',        'name_ar' => 'المهرجانات',        'icon_name' => 'celebration', 'color_value' => 0xFF2B4C8C, 'order' => 1, 'seed' => 'nb-festivals'],
            ['key' => 'music',       'name' => 'Concerts & Music', 'name_ar' => 'الحفلات والموسيقى', 'icon_name' => 'music_note',  'color_value' => 0xFF7B4FA0, 'order' => 2, 'seed' => 'nb-music'],
            ['key' => 'sports',      'name' => 'Sports',           'name_ar' => 'الرياضة',           'icon_name' => 'sports',      'color_value' => 0xFFE0533D, 'order' => 3, 'seed' => 'nb-sports'],
            ['key' => 'workshops',   'name' => 'Workshops',        'name_ar' => 'ورش العمل',         'icon_name' => 'school',      'color_value' => 0xFF00897B, 'order' => 4, 'seed' => 'nb-workshops'],
            ['key' => 'exhibitions', 'name' => 'Exhibitions',      'name_ar' => 'المعارض',           'icon_name' => 'palette',     'color_value' => 0xFFF4A521, 'order' => 5, 'seed' => 'nb-exhibitions'],
            ['key' => 'community',   'name' => 'Community',        'name_ar' => 'المجتمع',           'icon_name' => 'groups',      'color_value' => 0xFF4CAF50, 'order' => 6, 'seed' => 'nb-community'],
            ['key' => 'conferences', 'name' => 'Conferences',      'name_ar' => 'المؤتمرات',         'icon_name' => 'business',    'color_value' => 0xFF546E7A, 'order' => 7, 'seed' => 'nb-conferences'],
            ['key' => 'family',      'name' => 'Family & Kids',    'name_ar' => 'العائلة والأطفال',  'icon_name' => 'child_care',  'color_value' => 0xFFEC407A, 'order' => 8, 'seed' => 'nb-family'],
        ];
        $categories = [];
        foreach ($categoryDefs as $c) {
            $categories[$c['key']] = Category::create([
                'name' => $c['name'],
                'name_ar' => $c['name_ar'],
                'description' => $c['name'] . ' across North Batinah',
                'description_ar' => $c['name_ar'] . ' في محافظة شمال الباطنة',
                'icon_name' => $c['icon_name'],
                'color_value' => $c['color_value'],
                'image_url' => $this->img($c['seed']),
                'display_order' => $c['order'],
                'is_active' => true,
            ]);
        }

        // ------------------------------------------------------------------
        // Map locations (venues across North Batinah)
        // ------------------------------------------------------------------
        $mapDefs = [
            ['type' => 'stadium',      'name' => 'Sohar Sports Complex',    'name_ar' => 'مجمع صحار الرياضي',      'lat' => 24.3420, 'lng' => 56.7090, 'icon' => 'stadium',        'color' => 0xFFE0533D],
            ['type' => 'hall',         'name' => 'Sohar Convention Centre', 'name_ar' => 'مركز صحار للمؤتمرات',    'lat' => 24.3560, 'lng' => 56.7300, 'icon' => 'business',       'color' => 0xFF546E7A],
            ['type' => 'beach',        'name' => 'Sohar Corniche',          'name_ar' => 'كورنيش صحار',            'lat' => 24.3623, 'lng' => 56.7440, 'icon' => 'beach_access',   'color' => 0xFF03A9F4],
            ['type' => 'heritage',     'name' => 'Sohar Fort',              'name_ar' => 'قلعة صحار',              'lat' => 24.3570, 'lng' => 56.7090, 'icon' => 'castle',         'color' => 0xFF8D6E3A],
            ['type' => 'park',         'name' => 'Saham Public Park',       'name_ar' => 'حديقة صحم العامة',       'lat' => 24.1720, 'lng' => 56.8880, 'icon' => 'park',           'color' => 0xFF4CAF50],
            ['type' => 'venue',        'name' => 'Liwa Cultural Centre',    'name_ar' => 'مركز لوى الثقافي',       'lat' => 24.5300, 'lng' => 56.5600, 'icon' => 'account_balance','color' => 0xFF2B4C8C],
            ['type' => 'beach',        'name' => 'Shinas Waterfront',       'name_ar' => 'واجهة شناص البحرية',     'lat' => 24.7420, 'lng' => 56.4670, 'icon' => 'sailing',        'color' => 0xFF00897B],
            ['type' => 'venue',        'name' => 'Al Suwaiq Exhibition Hall','name_ar' => 'قاعة معارض السويق',     'lat' => 23.8490, 'lng' => 57.4380, 'icon' => 'storefront',     'color' => 0xFFF4A521],
            ['type' => 'parking',      'name' => 'Central Parking',         'name_ar' => 'الموقف المركزي',         'lat' => 24.3555, 'lng' => 56.7300, 'icon' => 'local_parking',  'color' => 0xFF607D8B],
            ['type' => 'info',         'name' => 'Information Desk',         'name_ar' => 'مكتب المعلومات',         'lat' => 24.3562, 'lng' => 56.7305, 'icon' => 'info',           'color' => 0xFF2196F3],
        ];
        $map = [];
        foreach ($mapDefs as $m) {
            $loc = MapLocation::create([
                'type' => $m['type'],
                'name' => $m['name'],
                'name_ar' => $m['name_ar'],
                'description' => $m['name'] . ', North Batinah',
                'description_ar' => $m['name_ar'] . '، شمال الباطنة',
                'latitude' => $m['lat'],
                'longitude' => $m['lng'],
                'icon' => $m['icon'],
                'color' => $m['color'],
                'is_active' => true,
            ]);
            $map[$m['name']] = $loc->id;
        }

        // ------------------------------------------------------------------
        // Events — a general catalogue across North Batinah wilayats.
        // price 0 = free registration; price > 0 = paid ticket.
        // cap -1 = unlimited registration.
        // ------------------------------------------------------------------
        $stdPricing = fn ($price) => [
            ['type' => 'standard', 'price' => $price, 'quantity' => 500, 'benefits' => 'General admission', 'benefits_ar' => 'دخول عام'],
            ['type' => 'vip', 'price' => $price > 0 ? $price * 3 : 5, 'quantity' => 80, 'benefits' => 'VIP seating + refreshments', 'benefits_ar' => 'مقاعد كبار الشخصيات + مرطبات'],
        ];

        $eventDefs = [
            ['t' => 'Sohar Festival 2025',            't_ar' => 'مهرجان صحار 2025',            'cat' => 'festivals',   'venue' => 'Sohar Corniche',          'wilayat' => 'Sohar',       'day' => 0,  'sh' => 17, 'dur' => 6, 'price' => 2, 'cap' => 5000, 'feat' => true,  'seed' => 'sohar-festival', 'd' => 'The flagship North Batinah festival returns to Sohar with music, heritage, food stalls, funfair rides and a nightly fireworks show. Register to attend.', 'd_ar' => 'يعود مهرجان شمال الباطنة الأبرز إلى صحار بالموسيقى والتراث وأكشاك الطعام والألعاب وعرض ناري ليلي. سجّل للحضور.'],
            ['t' => 'North Batinah Marathon',         't_ar' => 'ماراثون شمال الباطنة',        'cat' => 'sports',      'venue' => 'Sohar Corniche',          'wilayat' => 'Sohar',       'day' => 4,  'sh' => 6,  'dur' => 4, 'price' => 5, 'cap' => 1500, 'feat' => true,  'seed' => 'nb-marathon',    'd' => 'Run the annual North Batinah Marathon along the Sohar Corniche — 5km, 10km and full-marathon categories.', 'd_ar' => 'شارك في ماراثون شمال الباطنة السنوي على كورنيش صحار — فئات 5 كم و10 كم والماراثون الكامل.'],
            ['t' => 'Omani Heritage Concert',         't_ar' => 'حفل التراث العماني',          'cat' => 'music',       'venue' => 'Sohar Fort',              'wilayat' => 'Sohar',       'day' => 2,  'sh' => 20, 'dur' => 3, 'price' => 3, 'cap' => 1200, 'feat' => true,  'seed' => 'heritage-concert','d' => 'An evening of Omani folk music and Al-Bar\'ah performances staged against the walls of Sohar Fort.', 'd_ar' => 'أمسية من الموسيقى الشعبية العمانية وعروض البرعة على خلفية أسوار قلعة صحار.'],
            ['t' => 'Liwa Date Harvest Fair',         't_ar' => 'مهرجان حصاد التمور بلوى',      'cat' => 'festivals',   'venue' => 'Liwa Cultural Centre',    'wilayat' => 'Liwa',        'day' => 6,  'sh' => 16, 'dur' => 6, 'price' => 0, 'cap' => -1,   'feat' => false, 'seed' => 'date-harvest',   'd' => 'Celebrate the date harvest season with tastings, a farmers\' market and traditional demonstrations in Liwa.', 'd_ar' => 'احتفل بموسم حصاد التمور مع التذوق وسوق المزارعين والعروض التقليدية في لوى.'],
            ['t' => 'Shinas Fishing Championship',    't_ar' => 'بطولة شناص لصيد الأسماك',      'cat' => 'sports',      'venue' => 'Shinas Waterfront',       'wilayat' => 'Shinas',      'day' => 8,  'sh' => 5,  'dur' => 6, 'price' => 3, 'cap' => 300,  'feat' => false, 'seed' => 'fishing-champ',  'd' => 'Local fishing crews compete in the annual Shinas coastal fishing championship.', 'd_ar' => 'تتنافس فرق الصيد المحلية في بطولة شناص السنوية للصيد الساحلي.'],
            ['t' => 'Al Suwaiq Agricultural Show',    't_ar' => 'معرض السويق الزراعي',         'cat' => 'community',   'venue' => 'Al Suwaiq Exhibition Hall','wilayat' => 'Al Suwaiq',  'day' => 5,  'sh' => 16, 'dur' => 5, 'price' => 0, 'cap' => 2000, 'feat' => false, 'seed' => 'agri-show',      'd' => 'Showcasing North Batinah\'s farms, livestock, produce and modern agriculture technology.', 'd_ar' => 'عرض لمزارع شمال الباطنة والثروة الحيوانية والمنتجات وتقنيات الزراعة الحديثة.'],
            ['t' => 'North Batinah Business Forum',    't_ar' => 'منتدى أعمال شمال الباطنة',    'cat' => 'conferences', 'venue' => 'Sohar Convention Centre', 'wilayat' => 'Sohar',       'day' => 3,  'sh' => 9,  'dur' => 8, 'price' => 10,'cap' => 400,  'feat' => true,  'seed' => 'business-forum', 'd' => 'Leaders and entrepreneurs discuss investment, logistics and the future of the Sohar economic zone.', 'd_ar' => 'قادة ورواد أعمال يناقشون الاستثمار واللوجستيات ومستقبل منطقة صحار الاقتصادية.'],
            ['t' => 'Kids Science & Robotics Lab',    't_ar' => 'مختبر العلوم والروبوت للأطفال','cat' => 'workshops',   'venue' => 'Sohar Convention Centre', 'wilayat' => 'Sohar',       'day' => 7,  'sh' => 10, 'dur' => 3, 'price' => 2, 'cap' => 120,  'feat' => false, 'seed' => 'kids-robotics',  'd' => 'A hands-on STEM workshop where children build and program simple robots. Ages 8–14.', 'd_ar' => 'ورشة علمية تطبيقية يبني فيها الأطفال ويبرمجون روبوتات بسيطة. للأعمار 8–14.'],
            ['t' => 'Contemporary Art Exhibition',    't_ar' => 'معرض الفن المعاصر',           'cat' => 'exhibitions', 'venue' => 'Sohar Convention Centre', 'wilayat' => 'Sohar',       'day' => 1,  'sh' => 16, 'dur' => 6, 'price' => 1, 'cap' => 600,  'feat' => false, 'seed' => 'art-expo',       'd' => 'Works by emerging Omani artists exploring identity, sea and landscape.', 'd_ar' => 'أعمال لفنانين عمانيين ناشئين تستكشف الهوية والبحر والطبيعة.'],
            ['t' => 'Traditional Crafts Workshop',    't_ar' => 'ورشة الحرف التقليدية',        'cat' => 'workshops',   'venue' => 'Liwa Cultural Centre',    'wilayat' => 'Al Khaburah', 'day' => 9,  'sh' => 17, 'dur' => 3, 'price' => 2, 'cap' => 60,   'feat' => false, 'seed' => 'crafts-workshop', 'd' => 'Learn palm-frond weaving and silver-work from master Omani artisans.', 'd_ar' => 'تعلّم سعف النخيل وصناعة الفضة على يد حرفيين عمانيين مهرة.'],
            ['t' => 'Beach Volleyball Tournament',    't_ar' => 'بطولة الكرة الطائرة الشاطئية','cat' => 'sports',      'venue' => 'Sohar Corniche',          'wilayat' => 'Sohar',       'day' => 10, 'sh' => 16, 'dur' => 4, 'price' => 0, 'cap' => 500,  'feat' => false, 'seed' => 'beach-volley',   'd' => 'Community beach volleyball tournament on the Sohar Corniche — teams and spectators welcome.', 'd_ar' => 'بطولة مجتمعية للكرة الطائرة الشاطئية على كورنيش صحار — الفرق والجمهور مرحّب بهم.'],
            ['t' => 'Family Fun Day',                 't_ar' => 'يوم المرح العائلي',           'cat' => 'family',      'venue' => 'Saham Public Park',       'wilayat' => 'Saham',       'day' => 2,  'sh' => 16, 'dur' => 5, 'price' => 1, 'cap' => 1500, 'feat' => false, 'seed' => 'family-fun',     'd' => 'Rides, games, face painting and food trucks for the whole family at Saham Public Park.', 'd_ar' => 'ألعاب ومرح ورسم على الوجه وعربات طعام لكل العائلة في حديقة صحم العامة.'],
            ['t' => 'Startup Pitch Night',            't_ar' => 'ليلة عروض الشركات الناشئة',   'cat' => 'conferences', 'venue' => 'Sohar Convention Centre', 'wilayat' => 'Sohar',       'day' => 12, 'sh' => 18, 'dur' => 3, 'price' => 0, 'cap' => 200,  'feat' => false, 'seed' => 'pitch-night',    'd' => 'North Batinah founders pitch to investors and a live audience. Register to attend or pitch.', 'd_ar' => 'مؤسسون من شمال الباطنة يقدّمون عروضهم للمستثمرين وجمهور مباشر. سجّل للحضور أو التقديم.'],
            ['t' => 'Sunset Photography Walk',        't_ar' => 'جولة تصوير الغروب',           'cat' => 'workshops',   'venue' => 'Sohar Fort',              'wilayat' => 'Sohar',       'day' => 11, 'sh' => 17, 'dur' => 2, 'price' => 1, 'cap' => 40,   'feat' => false, 'seed' => 'photo-walk',     'd' => 'A guided golden-hour photography walk around historic Sohar Fort. All skill levels welcome.', 'd_ar' => 'جولة تصوير موجّهة وقت الغروب حول قلعة صحار التاريخية. لكل المستويات.'],
        ];

        foreach ($eventDefs as $e) {
            $start = (clone $now)->addDays($e['day'])->setTime($e['sh'], 0);
            $end = (clone $start)->addHours($e['dur']);
            // The flagship festival runs across the whole season so purchased
            // tickets stay valid throughout.
            if ($e['t'] === 'Sohar Festival 2025') {
                $start = (clone $now)->subDays(2)->setTime(17, 0);
                $end = (clone $now)->addDays(5)->setTime(23, 0);
            }
            Event::create([
                'title' => $e['t'],
                'title_ar' => $e['t_ar'],
                'description' => $e['d'],
                'description_ar' => $e['d_ar'],
                'category_id' => $categories[$e['cat']]->id,
                'start_time' => $start,
                'end_time' => $end,
                'location' => $e['venue'] . ', ' . $e['wilayat'],
                'location_ar' => $e['venue'],
                'map_location_id' => $map[$e['venue']] ?? null,
                'image_url' => $this->img($e['seed']),
                'images' => [$this->img($e['seed'] . '-a'), $this->img($e['seed'] . '-b'), $this->img($e['seed'] . '-c')],
                'price' => $e['price'],
                'currency' => 'OMR',
                'available_tickets' => $e['cap'],
                'total_tickets' => $e['cap'],
                'organizer_name' => 'North Batinah Governorate',
                'organizer_name_ar' => 'محافظة شمال الباطنة',
                'is_featured' => $e['feat'],
                'is_active' => true,
                'pricing' => $stdPricing($e['price']),
            ]);
        }

        // ------------------------------------------------------------------
        // Restaurants (general North Batinah dining)
        // ------------------------------------------------------------------
        $restaurantDefs = [
            ['n' => 'Bin Ateeq Restaurant',   'n_ar' => 'مطعم بن عتيق',              'cui' => 'Omani',        'cui_ar' => 'عماني',           'loc' => 'Sohar',      'rate' => 4.6, 'rc' => 214, 'pr' => '$$',  'feat' => true,  'seed' => 'bin-ateeq',    'd' => 'Traditional Omani dishes served in private majlis rooms — famous for shuwa and harees.', 'd_ar' => 'أطباق عمانية تقليدية تُقدّم في مجالس خاصة، مشهور بالشواء والهريس.'],
            ['n' => 'Al Mina Seafood',        'n_ar' => 'مطعم الميناء للمأكولات البحرية','cui' => 'Seafood',   'cui_ar' => 'مأكولات بحرية',   'loc' => 'Sohar Port', 'rate' => 4.4, 'rc' => 168, 'pr' => '$$$', 'feat' => true,  'seed' => 'al-mina',      'd' => 'Fresh catch of the day grilled to order with a view over Sohar port.', 'd_ar' => 'أطباق بحرية طازجة تُشوى حسب الطلب مع إطلالة على ميناء صحار.'],
            ['n' => 'Corniche Café',          'n_ar' => 'مقهى الكورنيش',             'cui' => 'Café',         'cui_ar' => 'مقهى',            'loc' => 'Sohar',      'rate' => 4.2, 'rc' => 96,  'pr' => '$',   'feat' => false, 'seed' => 'corniche-cafe','d' => 'Omani kahwa, karak and fresh juices with a relaxed seaside setting.', 'd_ar' => 'قهوة عمانية وكرك وعصائر طازجة في أجواء بحرية هادئة.'],
            ['n' => 'Saham Turkish Grill',    'n_ar' => 'مشاوي صحم التركية',         'cui' => 'Turkish',      'cui_ar' => 'تركي',            'loc' => 'Saham',      'rate' => 4.3, 'rc' => 140, 'pr' => '$$',  'feat' => false, 'seed' => 'turkish-grill','d' => 'Charcoal kebabs, pide and baklava in a lively family atmosphere.', 'd_ar' => 'كباب الفحم والبيدا والبقلاوة في أجواء عائلية مفعمة بالحيوية.'],
            ['n' => 'Spice of India',         'n_ar' => 'نكهة الهند',                'cui' => 'Indian',       'cui_ar' => 'هندي',            'loc' => 'Al Suwaiq',  'rate' => 4.1, 'rc' => 205, 'pr' => '$',   'feat' => false, 'seed' => 'spice-india',  'd' => 'Authentic North and South Indian curries, biryani and fresh tandoori breads.', 'd_ar' => 'أطباق هندية أصيلة من الكاري والبرياني والخبز التندوري الطازج.'],
            ['n' => 'Golden Tulip Terrace',   'n_ar' => 'شرفة جولدن توليب',          'cui' => 'International', 'cui_ar' => 'عالمي',           'loc' => 'Sohar',      'rate' => 4.5, 'rc' => 132, 'pr' => '$$$', 'feat' => true,  'seed' => 'golden-tulip', 'd' => 'International buffet and à la carte dining with rooftop views.', 'd_ar' => 'بوفيه عالمي وأطباق منوعة مع إطلالة من السطح.'],
        ];
        $hours = [
            ['day' => 'Sun-Thu', 'day_ar' => 'الأحد-الخميس', 'opening' => '12:00', 'closing' => '23:30'],
            ['day' => 'Fri-Sat', 'day_ar' => 'الجمعة-السبت', 'opening' => '13:00', 'closing' => '00:30'],
        ];
        foreach ($restaurantDefs as $r) {
            Restaurant::create([
                'name' => $r['n'],
                'name_ar' => $r['n_ar'],
                'description' => $r['d'],
                'description_ar' => $r['d_ar'],
                'cuisine' => $r['cui'],
                'cuisine_ar' => $r['cui_ar'],
                'location' => $r['loc'],
                'location_ar' => $r['loc'],
                'latitude' => 24.3540 + (mt_rand(-400, 400) / 10000),
                'longitude' => 56.7100 + (mt_rand(-400, 400) / 10000),
                'rating' => $r['rate'],
                'total_ratings' => $r['rc'],
                'price_range' => $r['pr'],
                'image_url' => $this->img($r['seed']),
                'images' => [$this->img($r['seed'] . '-a'), $this->img($r['seed'] . '-b')],
                'phone' => '+968 2684 ' . mt_rand(1000, 9999),
                'website' => 'https://northbatinah.events',
                'features' => ['Family section', 'Takeaway', 'Card payment'],
                'opening_hours' => $hours,
                'menu_url' => 'https://northbatinah.events/menu',
                'is_open' => true,
                'has_delivery' => true,
                'has_parking' => true,
                'has_wifi' => true,
                'is_featured' => $r['feat'],
                'is_active' => true,
            ]);
        }

        // ------------------------------------------------------------------
        // Unified locations table (services, emergency, parking, first aid)
        // ------------------------------------------------------------------
        $locationDefs = [
            ['type' => 'emergency', 'name' => 'Royal Oman Police', 'name_ar' => 'شرطة عمان السلطانية', 'phone' => '9999', 'addr' => 'Sohar'],
            ['type' => 'emergency', 'name' => 'Ambulance & Rescue', 'name_ar' => 'الإسعاف والإنقاذ', 'phone' => '9999', 'addr' => 'Sohar'],
            ['type' => 'first_aid', 'name' => 'Event First Aid', 'name_ar' => 'إسعافات الفعالية', 'phone' => '+968 2684 0111', 'addr' => 'Main Venue'],
            ['type' => 'parking', 'name' => 'Central Parking', 'name_ar' => 'الموقف المركزي', 'phone' => null, 'addr' => 'Sohar Convention Centre'],
            ['type' => 'service', 'name' => 'Lost & Found', 'name_ar' => 'المفقودات', 'phone' => '+968 2684 0122', 'addr' => 'Information Desk'],
        ];
        foreach ($locationDefs as $l) {
            DB::table('locations')->insert([
                'name' => $l['name'],
                'name_ar' => $l['name_ar'],
                'type' => $l['type'],
                'description' => $l['name'],
                'description_ar' => $l['name_ar'],
                'latitude' => 24.3560 + (mt_rand(-200, 200) / 10000),
                'longitude' => 56.7300 + (mt_rand(-200, 200) / 10000),
                'address' => $l['addr'],
                'address_ar' => $l['addr'],
                'contact_number' => $l['phone'],
                'additional_info' => json_encode(['is_24_hours' => $l['type'] === 'emergency']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ------------------------------------------------------------------
        // Announcements (platform-wide)
        // ------------------------------------------------------------------
        $announcementDefs = [
            ['t' => 'Welcome to North Batinah Events', 't_ar' => 'مرحباً بكم في فعاليات شمال الباطنة', 'c' => 'Discover and register for events across Sohar, Saham, Liwa, Shinas and Al Suwaiq.', 'c_ar' => 'اكتشف وسجّل في الفعاليات عبر صحار وصحم ولوى وشناص والسويق.', 'type' => 'info', 'prio' => 2, 'pin' => true],
            ['t' => 'Sohar Festival 2025 registration open', 't_ar' => 'التسجيل مفتوح لمهرجان صحار 2025', 'c' => 'Register now for the Sohar Festival 2025 — limited daily capacity.', 'c_ar' => 'سجّل الآن في مهرجان صحار 2025 — السعة اليومية محدودة.', 'type' => 'celebration', 'prio' => 3, 'pin' => true],
            ['t' => 'Free parking at all venues', 't_ar' => 'مواقف مجانية في جميع المواقع', 'c' => 'Free parking is available at all event venues across North Batinah.', 'c_ar' => 'تتوفر مواقف مجانية في جميع مواقع الفعاليات في شمال الباطنة.', 'type' => 'info', 'prio' => 1, 'pin' => false],
            ['t' => 'Weather advisory', 't_ar' => 'تنبيه الطقس', 'c' => 'Evenings may be humid — please stay hydrated at outdoor events.', 'c_ar' => 'قد تكون الأمسيات رطبة — يرجى شرب الماء في الفعاليات الخارجية.', 'type' => 'warning', 'prio' => 1, 'pin' => false],
        ];
        foreach ($announcementDefs as $a) {
            Announcement::create([
                'title' => $a['t'],
                'title_ar' => $a['t_ar'],
                'content' => $a['c'],
                'content_ar' => $a['c_ar'],
                'type' => $a['type'],
                'priority' => $a['prio'],
                'is_pinned' => $a['pin'],
                'is_active' => true,
                'start_datetime' => $now,
                'end_datetime' => (clone $now)->addDays(30),
                'created_by' => 1,
            ]);
        }

        // ------------------------------------------------------------------
        // Demo visitor with personal data (registered/purchased events,
        // favorites, notifications). Log in with phone 91234567 + test OTP.
        // ------------------------------------------------------------------
        $demo = User::firstOrCreate(
            ['phone_number' => '91234567'],
            [
                'name' => 'أحمد الزدجالي',
                'email' => 'visitor@northbatinah.events',
                'password' => Hash::make('visitor123'),
                'phone_verified_at' => $now,
                'preferred_language' => 'ar',
            ]
        );

        $soharFestival = Event::where('title', 'Sohar Festival 2025')->first();
        $featuredRestaurant = Restaurant::where('is_featured', true)->first();

        if ($soharFestival) {
            $txn = 'TXN' . strtoupper(Str::random(10));
            Payment::create([
                'user_id' => $demo->id,
                'transaction_id' => $txn,
                'payment_type' => 'ticket',
                'payable_id' => $soharFestival->id,
                'payable_type' => 'App\\Models\\Ticket',
                'amount' => (float) $soharFestival->price * 2,
                'currency' => 'OMR',
                'status' => 'completed',
                'payment_method' => 'card',
                'paid_at' => $now,
                'metadata' => ['quantity' => 2, 'ticket_type' => 'standard', 'booking_date' => (clone $now)->addDay()->format('Y-m-d')],
            ]);
            for ($i = 0; $i < 2; $i++) {
                Ticket::create([
                    'user_id' => $demo->id,
                    'event_id' => $soharFestival->id,
                    'transaction_id' => $txn,
                    'booking_date' => (clone $now)->addDay()->format('Y-m-d'),
                    'max_check_ins' => 1,
                    'quantity' => 1,
                    'ticket_type' => 'standard',
                    'status' => 'active',
                    'price' => (float) $soharFestival->price,
                    'currency' => 'OMR',
                    'holder_name' => $demo->name,
                    'purchase_date' => $now,
                ]);
            }
            UserFavorite::create([
                'user_id' => $demo->id,
                'favoritable_type' => Event::class,
                'favoritable_id' => $soharFestival->id,
            ]);
        }
        if ($featuredRestaurant) {
            UserFavorite::create([
                'user_id' => $demo->id,
                'favoritable_type' => Restaurant::class,
                'favoritable_id' => $featuredRestaurant->id,
            ]);
        }

        $notificationDefs = [
            ['t' => 'Welcome to North Batinah Events!', 't_ar' => 'مرحباً بك في فعاليات شمال الباطنة!', 'b' => 'Browse events, register or buy tickets, and manage them all in one place.', 'b_ar' => 'تصفّح الفعاليات وسجّل أو اشترِ التذاكر وأدرها في مكان واحد.', 'type' => 'info', 'read' => true],
            ['t' => 'Registration confirmed', 't_ar' => 'تم تأكيد التسجيل', 'b' => 'Your 2 tickets for Sohar Festival 2025 are ready. Show the QR at the gate.', 'b_ar' => 'تذكرتاك لمهرجان صحار 2025 جاهزتان. أظهر رمز QR عند البوابة.', 'type' => 'ticket', 'read' => false],
            ['t' => 'Event reminder', 't_ar' => 'تذكير بالفعالية', 'b' => 'Omani Heritage Concert is coming up soon at Sohar Fort.', 'b_ar' => 'حفل التراث العماني يقترب قريباً في قلعة صحار.', 'type' => 'event', 'read' => false],
            ['t' => 'Payment received', 't_ar' => 'تم استلام الدفع', 'b' => 'We received your payment of 4.000 OMR. Thank you!', 'b_ar' => 'استلمنا دفعتك بقيمة 4.000 ريال عماني. شكراً لك!', 'type' => 'payment', 'read' => true],
        ];
        foreach ($notificationDefs as $n) {
            Notification::create([
                'user_id' => $demo->id,
                'title' => $n['t'],
                'title_ar' => $n['t_ar'],
                'body' => $n['b'],
                'body_ar' => $n['b_ar'],
                'type' => $n['type'],
                'data' => null,
                'is_read' => $n['read'],
                'read_at' => $n['read'] ? $now : null,
            ]);
        }

        // ------------------------------------------------------------------
        // App settings / config
        // ------------------------------------------------------------------
        $settings = [
            ['setting_key' => 'app_name', 'setting_value' => 'فعاليات شمال الباطنة', 'setting_type' => 'string', 'is_public' => true],
            ['setting_key' => 'app_name_en', 'setting_value' => 'North Batinah Events', 'setting_type' => 'string', 'is_public' => true],
            ['setting_key' => 'primary_color', 'setting_value' => '#2B4C8C', 'setting_type' => 'string', 'is_public' => true],
            ['setting_key' => 'secondary_color', 'setting_value' => '#F4A521', 'setting_type' => 'string', 'is_public' => true],
            ['setting_key' => 'support_phone', 'setting_value' => '+968 2684 0000', 'setting_type' => 'string', 'is_public' => true],
            ['setting_key' => 'support_email', 'setting_value' => 'info@northbatinah.events', 'setting_type' => 'string', 'is_public' => true],
        ];
        foreach ($settings as $s) {
            DB::table('app_settings')->updateOrInsert(
                ['setting_key' => $s['setting_key']],
                array_merge($s, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        echo "North Batinah Events data seeded: "
            . Category::count() . " categories, "
            . Event::count() . " events, "
            . Restaurant::count() . " restaurants, "
            . MapLocation::count() . " venues, "
            . Notification::count() . " notifications.\n";
    }
}
