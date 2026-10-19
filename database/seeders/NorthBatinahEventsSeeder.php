<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserFavorite;
use App\Support\TenantTime;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * North Al Batinah seed content (tenant `north-batinah`), built from real
 * events and places in the governorate (Sohar, Shinas, Liwa, Saham,
 * Al Khaburah, Al Suwaiq).
 *
 * Every fact, its source and whether it is verified or estimated is listed in
 * ../docs/north_batinah_seed_sources.md. In short: the dates are the 2026–27
 * season, estimated from each event's most recent edition (only National Day,
 * 20–21 Nov, is fixed by royal decree). Coordinates come from OpenStreetMap.
 * Stall and restaurant names are generic, not real businesses.
 *
 * Times are tenant-local wall-clock times (Asia/Muscat). When the seeder runs
 * after the season (31 May 2027), every date moves forward by whole years so
 * the demo stays upcoming.
 *
 * Idempotent: natural keys with updateOrCreate / firstOrCreate. Events are
 * keyed by title and never overwritten, so admin edits survive a re-seed.
 */
class NorthBatinahEventsSeeder extends Seeder
{
    /** Benefits line of the standard ticket type: [en, ar]. */
    public const PRICING_BENEFITS = [
        'paid' => ['Admission for one person, one day', 'دخول شخص واحد ليوم واحد'],
        'free' => ['Free registration', 'تسجيل مجاني'],
    ];

    private static function img(string $seed, int $w = 800, int $h = 600): string
    {
        return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
    }

    /**
     * The season's seed content, without touching the database. run() seeds
     * it; `content:sync-arabic` reads the Arabic texts from here to update
     * rows seeded earlier (matched by their English natural keys).
     */
    public static function content(?Carbon $now = null): array
    {
        $tz = TenantTime::zone();
        $now = $now ? $now->copy()->setTimezone($tz) : Carbon::now($tz);

        // Season anchor: the 2026–27 season. Shift by whole years once it has passed.
        $shift = 0;
        while (Carbon::parse('2027-05-31 23:59', $tz)->addYears($shift)->lt($now)) {
            $shift++;
        }
        $at = fn (string $local) => Carbon::parse($local, $tz)->addYears($shift);
        $season = (2026 + $shift).'–'.substr((string) (2027 + $shift), 2);
        $seasonAr = (2026 + $shift).'–'.(2027 + $shift);

        // ------------------------------------------------------------------
        // Categories
        // ------------------------------------------------------------------
        $categoryDefs = [
            ['key' => 'festivals', 'name' => 'Festivals',          'name_ar' => 'المهرجانات',        'icon' => 'celebration', 'color' => 0xFF2F8E80, 'd' => 'Seasonal festivals across North Al Batinah', 'd_ar' => 'المهرجانات الموسمية في محافظة شمال الباطنة'],
            ['key' => 'heritage',  'name' => 'Heritage & Culture', 'name_ar' => 'التراث والثقافة',   'icon' => 'museum',      'color' => 0xFF8D6E3A, 'd' => 'Omani heritage, crafts, folk arts and culture', 'd_ar' => 'التراث العماني والحرف والفنون الشعبية والثقافة'],
            ['key' => 'family',    'name' => 'Family & Kids',      'name_ar' => 'العائلة والأطفال',  'icon' => 'family',      'color' => 0xFFEC407A, 'd' => 'Activities for children and families', 'd_ar' => 'أنشطة للأطفال والعائلات'],
            ['key' => 'sports',    'name' => 'Sports & Racing',    'name_ar' => 'الرياضة والسباقات', 'icon' => 'sports',      'color' => 0xFFE0533D, 'd' => 'Motorsport, cycling, camel and horse racing', 'd_ar' => 'رياضة المحركات والدراجات وسباقات الهجن والخيل'],
            ['key' => 'entertainment', 'name' => 'Entertainment',  'name_ar' => 'الترفيه',           'icon' => 'stage',       'color' => 0xFF7B4FA0, 'd' => 'Shows, carnival parades and fun experiences', 'd_ar' => 'عروض ومسيرات كرنفالية وأنشطة ترفيهية'],
            ['key' => 'national',  'name' => 'National Occasions', 'name_ar' => 'المناسبات الوطنية', 'icon' => 'celebration', 'color' => 0xFFC62828, 'd' => 'National Day and national celebrations', 'd_ar' => 'العيد الوطني والاحتفالات الوطنية'],
            ['key' => 'arts',      'name' => 'Arts & Exhibitions', 'name_ar' => 'الفنون والمعارض',   'icon' => 'palette',     'color' => 0xFFF4A521, 'd' => 'Theatre, photography and exhibitions', 'd_ar' => 'المسرح والتصوير والمعارض'],
            ['key' => 'markets',   'name' => 'Markets & Souqs',    'name_ar' => 'الأسواق والهبطات',  'icon' => 'market',      'color' => 0xFF546E7A, 'd' => 'Traditional markets and seasonal habtas', 'd_ar' => 'الأسواق التقليدية والهبطات الموسمية'],
        ];
        // ------------------------------------------------------------------
        // Map locations: real places (OpenStreetMap coordinates). Types and
        // icons are from MapLocation::getTypes() / the admin form.
        // Festival-ground POIs (stage, village, food court...) are indicative
        // positions inside the Sohar Entertainment Centre grounds.
        // ------------------------------------------------------------------
        $g = ['lat' => 24.33927, 'lng' => 56.68458]; // Sohar Entertainment Centre (approx.)
        $mapDefs = [
            // Sohar festival grounds
            ['key' => 'grounds',  'type' => 'venue',         'icon' => 'stage',            'color' => 0xFF2F8E80, 'lat' => $g['lat'],           'lng' => $g['lng'],           'n' => 'Sohar Entertainment Centre (Sohar Festival grounds)', 'n_ar' => 'مركز صحار الترفيهي (أرض مهرجان صحار)', 'd' => 'Home of Sohar Festival and base of the Oman International Rally in Sohar.', 'd_ar' => 'مقر مهرجان صحار ومركز رالي عُمان الدولي في صحار.'],
            ['key' => 'stage',    'type' => 'stage',         'icon' => 'theater_comedy',   'color' => 0xFF9C27B0, 'lat' => $g['lat'] + 0.0007,  'lng' => $g['lng'] + 0.0004,  'n' => 'Sohar Festival Main Stage', 'n_ar' => 'المسرح الرئيسي لمهرجان صحار', 'd' => 'Opening night, carnival shows and evening programmes.', 'd_ar' => 'حفل الافتتاح والعروض الكرنفالية والبرامج المسائية.'],
            ['key' => 'village',  'type' => 'heritage',      'icon' => 'museum',           'color' => 0xFF8D6E63, 'lat' => $g['lat'] - 0.0006,  'lng' => $g['lng'] + 0.0008,  'n' => 'Omani Village', 'n_ar' => 'القرية العمانية', 'd' => 'Crafts, customs and folk food from the wilayats of North Al Batinah.', 'd_ar' => 'الحرف والعادات والمأكولات الشعبية من ولايات شمال الباطنة.'],
            ['key' => 'kids',     'type' => 'entertainment', 'icon' => 'celebration',      'color' => 0xFFFF9800, 'lat' => $g['lat'] + 0.0004,  'lng' => $g['lng'] - 0.0007,  'n' => 'Child & Family Theatre', 'n_ar' => 'مسرح الطفل والأسرة', 'd' => 'Daily educational and fun shows for children.', 'd_ar' => 'عروض تربوية وترفيهية يومية للأطفال.'],
            ['key' => 'karting',  'type' => 'entertainment', 'icon' => 'sports',           'color' => 0xFFE0533D, 'lat' => $g['lat'] - 0.0012,  'lng' => $g['lng'] - 0.0006,  'n' => 'Festival Karting Track', 'n_ar' => 'مضمار الكارتينج', 'd' => 'Paid karting sessions during the festival.', 'd_ar' => 'جولات كارتينج مدفوعة خلال المهرجان.'],
            ['key' => 'food',     'type' => 'food',          'icon' => 'restaurant',       'color' => 0xFFFFC107, 'lat' => $g['lat'] - 0.0002,  'lng' => $g['lng'] + 0.0013,  'n' => 'Festival Food Court', 'n_ar' => 'ساحة المطاعم', 'd' => 'Food stalls, karak, Omani sweets and home-business kitchens.', 'd_ar' => 'أكشاك الطعام والكرك والحلوى العمانية ومطابخ الأسر المنتجة.'],
            ['key' => 'parking',  'type' => 'parking',       'icon' => 'local_parking',    'color' => 0xFF2196F3, 'lat' => $g['lat'] + 0.0016,  'lng' => $g['lng'] - 0.0012,  'n' => 'Festival Parking', 'n_ar' => 'مواقف المهرجان', 'd' => 'Main visitor car park for the festival grounds.', 'd_ar' => 'الموقف الرئيسي لزوار أرض المهرجان.'],
            ['key' => 'info',     'type' => 'info',          'icon' => 'info',             'color' => 0xFF00BCD4, 'lat' => $g['lat'] + 0.0010,  'lng' => $g['lng'] - 0.0002,  'n' => 'Festival Information Desk', 'n_ar' => 'مكتب معلومات المهرجان', 'd' => 'Visitor information, lost and found, wallet help.', 'd_ar' => 'استعلامات الزوار والمفقودات والمساعدة في استخدام المحفظة.'],
            ['key' => 'aid',      'type' => 'first_aid',     'icon' => 'medical_services', 'color' => 0xFFFF1744, 'lat' => $g['lat'] + 0.0009,  'lng' => $g['lng'] + 0.0011,  'n' => 'Festival First Aid Point', 'n_ar' => 'نقطة الإسعافات الأولية بالمهرجان', 'd' => 'First aid during festival hours.', 'd_ar' => 'إسعافات أولية طوال ساعات عمل المهرجان.'],
            // Sohar landmarks
            ['key' => 'sohar_fort',   'type' => 'fort',     'icon' => 'castle',        'color' => 0xFF6D4C41, 'lat' => 24.36203, 'lng' => 56.74929, 'n' => 'Sohar Fort', 'n_ar' => 'قلعة صحار', 'd' => 'Historic whitewashed fort on the Sohar seafront, with a museum on the city\'s history.', 'd_ar' => 'قلعة تاريخية بيضاء على واجهة صحار البحرية، تضم متحفًا لتاريخ المدينة.'],
            ['key' => 'sohar_souq',   'type' => 'shopping', 'icon' => 'shopping_bag',  'color' => 0xFFE91E63, 'lat' => 24.36552, 'lng' => 56.74795, 'n' => 'Sohar Souq', 'n_ar' => 'سوق صحار', 'd' => 'Traditional market by the fort and the seafront road.', 'd_ar' => 'السوق التقليدي بجوار القلعة والطريق البحري.'],
            ['key' => 'corniche',     'type' => 'beach',    'icon' => 'beach_access',  'color' => 0xFF4FC3F7, 'lat' => 24.37345, 'lng' => 56.74276, 'n' => 'Sohar Corniche', 'n_ar' => 'كورنيش صحار', 'd' => 'Seafront promenade along Al Bahri Road; finish of the 2026 Tour of Oman stage 4.', 'd_ar' => 'ممشى على الواجهة البحرية بمحاذاة الطريق البحري، وعنده خط نهاية المرحلة الرابعة من طواف عُمان 2026.'],
            ['key' => 'camel_track',  'type' => 'venue',    'icon' => 'stadium',       'color' => 0xFF8D6E3A, 'lat' => 24.31407, 'lng' => 56.64073, 'n' => 'Sohar Camel Racetrack', 'n_ar' => 'ميدان سباقات الهجن بصحار', 'd' => 'Camel racing and mazayna venue of Wilayat Sohar.', 'd_ar' => 'ميدان سباقات ومزاينة الهجن في ولاية صحار.'],
            ['key' => 'university',   'type' => 'venue',    'icon' => 'school',        'color' => 0xFF2B4C8C, 'lat' => 24.29889, 'lng' => 56.78149, 'n' => 'Sohar University', 'n_ar' => 'جامعة صحار', 'd' => 'University campus and theatre in Awtab, Sohar.', 'd_ar' => 'الحرم الجامعي والمسرح في عوتب بصحار.'],
            ['key' => 'stadium',      'type' => 'venue',    'icon' => 'stadium',       'color' => 0xFFE0533D, 'lat' => 24.33459, 'lng' => 56.74263, 'n' => 'Sohar Sports Complex', 'n_ar' => 'مجمع صحار الرياضي', 'd' => 'Sohar stadium and sports complex in Al Hambar.', 'd_ar' => 'استاد ومجمع صحار الرياضي في الهمبار.'],
            ['key' => 'governor',     'type' => 'facilities', 'icon' => 'business',    'color' => 0xFF795548, 'lat' => 24.34315, 'lng' => 56.73113, 'n' => 'North Al Batinah Governor\'s Office', 'n_ar' => 'مكتب محافظ شمال الباطنة', 'd' => 'Governorate office, organiser of Sohar Festival.', 'd_ar' => 'مكتب المحافظ، الجهة المنظمة لمهرجان صحار.'],
            ['key' => 'port',         'type' => 'facilities', 'icon' => 'sailing',     'color' => 0xFF00897B, 'lat' => 24.49083, 'lng' => 56.61424, 'n' => 'Sohar Port', 'n_ar' => 'ميناء صحار', 'd' => 'Deep-sea port and industrial area north of Sohar.', 'd_ar' => 'ميناء عميق ومنطقة صناعية شمال صحار.'],
            ['key' => 'freezone',     'type' => 'facilities', 'icon' => 'business',    'color' => 0xFF607D8B, 'lat' => 24.46542, 'lng' => 56.55593, 'n' => 'Sohar Freezone', 'n_ar' => 'المنطقة الحرة بصحار', 'd' => 'Free zone next to Sohar Port (Wilayat of Liwa).', 'd_ar' => 'منطقة حرة بجوار ميناء صحار في ولاية لوى.'],
            // Forts of the other wilayats
            ['key' => 'liwa_fort',     'type' => 'fort', 'icon' => 'castle', 'color' => 0xFF6D4C41, 'lat' => 24.52149, 'lng' => 56.57506, 'n' => 'Liwa Fort', 'n_ar' => 'حصن لوى', 'd' => 'Hilltop fort with five towers, historic seat of the wilayat.', 'd_ar' => 'حصن على تلة له خمسة أبراج، وهو المقر التاريخي للولاية.'],
            ['key' => 'shinas_fort',   'type' => 'fort', 'icon' => 'castle', 'color' => 0xFF6D4C41, 'lat' => 24.74421, 'lng' => 56.46637, 'n' => 'Shinas Fort', 'n_ar' => 'قلعة شناص', 'd' => 'Coastal fort of Wilayat Shinas.', 'd_ar' => 'القلعة الساحلية لولاية شناص.'],
            ['key' => 'saham_fort',    'type' => 'fort', 'icon' => 'castle', 'color' => 0xFF6D4C41, 'lat' => 24.17260, 'lng' => 56.89041, 'n' => 'Saham Fort', 'n_ar' => 'حصن صحم', 'd' => 'Historic fort in the centre of Saham.', 'd_ar' => 'حصن تاريخي في وسط صحم.'],
            ['key' => 'khaburah_fort', 'type' => 'fort', 'icon' => 'castle', 'color' => 0xFF6D4C41, 'lat' => 23.98225, 'lng' => 57.10559, 'n' => 'Al Khaburah Fort', 'n_ar' => 'حصن الخابورة', 'd' => 'Historic fort of Al Khaburah; its square hosts community gatherings.', 'd_ar' => 'حصن الخابورة التاريخي، وتستضيف ساحته فعاليات مجتمعية.'],
            ['key' => 'suwaiq_fort',   'type' => 'fort', 'icon' => 'castle', 'color' => 0xFF6D4C41, 'lat' => 23.84915, 'lng' => 57.43970, 'n' => 'Al Suwaiq Fort', 'n_ar' => 'حصن السويق', 'd' => 'Seaside fort of Al Suwaiq.', 'd_ar' => 'الحصن الساحلي لولاية السويق.'],
            ['key' => 'khaburah_club', 'type' => 'venue', 'icon' => 'stadium', 'color' => 0xFF8D6E3A, 'lat' => 23.94610, 'lng' => 57.12058, 'n' => 'Al Khaburah Equestrian Club (Al Breik)', 'n_ar' => 'نادي الخابورة للفروسية (البريك)', 'd' => 'Equestrian club in the Al Breik heights (approximate location).', 'd_ar' => 'نادي الفروسية في مرتفعات البريك (موقع تقريبي).'],
            // Emergency & health
            ['key' => 'sohar_hospital',  'type' => 'first_aid', 'icon' => 'medical_services', 'color' => 0xFFFF1744, 'lat' => 24.32590, 'lng' => 56.67817, 'n' => 'Sohar Hospital', 'n_ar' => 'مستشفى صحار', 'd' => 'Main referral hospital of North Al Batinah. Emergency: 9999.', 'd_ar' => 'المستشفى المرجعي الرئيسي في شمال الباطنة، ورقم الطوارئ 9999.'],
            ['key' => 'khaburah_health', 'type' => 'first_aid', 'icon' => 'medical_services', 'color' => 0xFFFF1744, 'lat' => 23.96184, 'lng' => 57.07872, 'n' => 'Al Khaburah Health Complex', 'n_ar' => 'مجمع الخابورة الصحي', 'd' => 'Health complex of Wilayat Al Khaburah.', 'd_ar' => 'المجمع الصحي لولاية الخابورة.'],
            ['key' => 'shinas_health',   'type' => 'first_aid', 'icon' => 'medical_services', 'color' => 0xFFFF1744, 'lat' => 24.70034, 'lng' => 56.45714, 'n' => 'Shinas Health Complex', 'n_ar' => 'مجمع شناص الصحي', 'd' => 'Health complex of Wilayat Shinas.', 'd_ar' => 'المجمع الصحي لولاية شناص.'],
            ['key' => 'suwaiq_health',   'type' => 'first_aid', 'icon' => 'medical_services', 'color' => 0xFFFF1744, 'lat' => 23.81737, 'lng' => 57.42308, 'n' => 'Al Suwaiq Health Complex', 'n_ar' => 'مجمع السويق الصحي', 'd' => 'Health complex of Wilayat Al Suwaiq.', 'd_ar' => 'المجمع الصحي لولاية السويق.'],
            ['key' => 'sohar_police',    'type' => 'emergency', 'icon' => 'emergency', 'color' => 0xFFF44336, 'lat' => 24.34061, 'lng' => 56.69853, 'n' => 'Sohar Police Station', 'n_ar' => 'مركز شرطة صحار', 'd' => 'Royal Oman Police. Emergency: 9999.', 'd_ar' => 'شرطة عُمان السلطانية، ورقم الطوارئ 9999.'],
            ['key' => 'police_command',  'type' => 'emergency', 'icon' => 'emergency', 'color' => 0xFFF44336, 'lat' => 24.33509, 'lng' => 56.69105, 'n' => 'North Al Batinah Police Command', 'n_ar' => 'قيادة شرطة محافظة شمال الباطنة', 'd' => 'Governorate police command in Sohar.', 'd_ar' => 'قيادة شرطة المحافظة في صحار.'],
        ];
        // ------------------------------------------------------------------
        // Events of the 2026–27 season. Dates are estimated from the latest
        // edition unless noted (see docs/north_batinah_seed_sources.md).
        // price 0 = free registration; cap -1 = unlimited (per day).
        // ------------------------------------------------------------------
        $festivalStart = $at('2026-12-22 16:00');
        $festivalEnd = $at('2027-01-31 23:59');
        $festivalTitle = "Sohar Festival {$season}";
        $festivalVenue = ['Sohar Entertainment Centre, Sohar', 'مركز صحار الترفيهي، صحار'];
        $gov = ['North Al Batinah Governor\'s Office', 'مكتب محافظ شمال الباطنة'];
        $included = ' Included with the Sohar Festival entry ticket.';
        $includedAr = ' والدخول مشمول بتذكرة مهرجان صحار.';

        $eventDefs = [
            [
                't' => $festivalTitle, 't_ar' => "مهرجان صحار {$seasonAr}", 'cat' => 'festivals', 'map' => 'grounds', 'venue' => $festivalVenue, 'org' => $gov,
                'start' => $festivalStart, 'end' => $festivalEnd, 'price' => 0.5, 'cap' => 15000, 'feat' => true, 'seed' => 'sohar-festival',
                'tags' => [['Sohar Festival', 'مهرجان صحار'], ['Family', 'عائلي'], ['Heritage', 'تراث'], ['School holiday', 'إجازة المدارس']],
                'd' => 'North Al Batinah\'s winter festival returns to the Sohar Entertainment Centre for the school holidays: an Omani village, carnival shows, a child & family theatre, karting, kite flying, a photography competition and stalls from local home businesses. The 4th edition (22 Dec 2025 – 31 Jan 2026) welcomed about half a million visitors. One entry ticket per day; some experiences are paid extras.',
                'd_ar' => 'يعود مهرجان شمال الباطنة الشتوي إلى مركز صحار الترفيهي في إجازة المدارس، ويضم القرية العمانية والعروض الكرنفالية ومسرح الطفل والأسرة والكارتينج والطائرات الورقية ومسابقة التصوير الضوئي وأركان الأسر المنتجة. واستقبلت النسخة الرابعة، من 22 ديسمبر 2025 إلى 31 يناير 2026، نحو نصف مليون زائر. لكل يوم تذكرة دخول، وبعض الأنشطة برسوم إضافية.',
            ],
            [
                't' => 'Sohar Festival: Opening Night', 't_ar' => 'ليلة افتتاح مهرجان صحار', 'cat' => 'festivals', 'map' => 'stage', 'venue' => ['Main Stage, Sohar Entertainment Centre', 'المسرح الرئيسي، مركز صحار الترفيهي'], 'org' => $gov,
                'start' => $at('2026-12-22 19:30'), 'end' => $at('2026-12-22 22:30'), 'price' => 0, 'cap' => 3000, 'feat' => true, 'seed' => 'sohar-opening',
                'tags' => [['Sohar Festival', 'مهرجان صحار'], ['Opening', 'افتتاح'], ['Folk arts', 'فنون شعبية']],
                'd' => 'The festival opens on the main stage with an operetta and tableaux drawn from the maritime, desert and folk heritage of North Al Batinah, following the format of previous openings. Free registration; seats are limited.',
                'd_ar' => 'يُفتتح المهرجان على المسرح الرئيسي بأوبريت ولوحات فنية مستوحاة من موروث شمال الباطنة البحري والبدوي والشعبي، على غرار حفلات الافتتاح السابقة. التسجيل مجاني والمقاعد محدودة.',
            ],
            [
                't' => 'Sohar Festival: Omani Village', 't_ar' => 'القرية العمانية في مهرجان صحار', 'cat' => 'heritage', 'map' => 'village', 'venue' => ['Omani Village, Sohar Entertainment Centre', 'القرية العمانية، مركز صحار الترفيهي'], 'org' => $gov,
                'start' => $festivalStart, 'end' => $festivalEnd, 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'omani-village',
                'tags' => [['Heritage', 'تراث'], ['Crafts', 'حرف يدوية'], ['Folk arts', 'فنون شعبية']],
                'd' => 'Bedouin, farming and seafaring corners with craftspeople, traditional customs and folk food. The wilayats of the governorate take turns presenting their folk arts, such as Razha, Ayyala and Maled.'.$included,
                'd_ar' => 'أركان تحاكي البيئات البدوية والزراعية والبحرية، فيها الحرفيون والعادات التقليدية والمأكولات الشعبية، وتتناوب ولايات المحافظة على تقديم فنونها الشعبية كالرزحة والعيالة والمالد،'.$includedAr,
            ],
            [
                't' => 'Sohar Festival: Child & Family Theatre', 't_ar' => 'مسرح الطفل والأسرة في مهرجان صحار', 'cat' => 'family', 'map' => 'kids', 'venue' => ['Child & Family Theatre, Sohar Entertainment Centre', 'مسرح الطفل والأسرة، مركز صحار الترفيهي'], 'org' => $gov,
                'start' => $festivalStart, 'end' => $festivalEnd, 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'kids-theatre',
                'tags' => [['Kids', 'أطفال'], ['Family', 'عائلي'], ['Theatre', 'مسرح']],
                'd' => 'Daily educational and entertaining shows for children and families during the school holidays.'.$included,
                'd_ar' => 'عروض تربوية وترفيهية يومية للأطفال والعائلات طوال إجازة المدارس،'.$includedAr,
            ],
            [
                't' => 'Sohar Festival: Carnival Shows', 't_ar' => 'العروض الكرنفالية في مهرجان صحار', 'cat' => 'entertainment', 'map' => 'stage', 'venue' => ['Festival grounds, Sohar Entertainment Centre', 'أرض المهرجان، مركز صحار الترفيهي'], 'org' => $gov,
                'start' => $at('2026-12-23 17:00'), 'end' => $festivalEnd, 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'carnival',
                'tags' => [['Carnival', 'كرنفال'], ['Family', 'عائلي']],
                'd' => 'Three carnival parades a day move through the festival grounds with costumed performers and music.'.$included,
                'd_ar' => 'تجوب أرض المهرجان ثلاث مسيرات كرنفالية كل يوم، يشارك فيها مؤدون بأزياء استعراضية على أنغام الموسيقى،'.$includedAr,
            ],
            [
                't' => 'Sohar Festival: Karting', 't_ar' => 'الكارتينج في مهرجان صحار', 'cat' => 'sports', 'map' => 'karting', 'venue' => ['Karting track, Sohar Entertainment Centre', 'مضمار الكارتينج، مركز صحار الترفيهي'], 'org' => $gov,
                'start' => $festivalStart, 'end' => $festivalEnd, 'price' => 2, 'cap' => 400, 'feat' => false, 'seed' => 'karting',
                'tags' => [['Motorsport', 'رياضة المحركات'], ['Paid experience', 'نشاط مدفوع']],
                'd' => 'Karting sessions for motorsport fans, one of the festival\'s optional paid experiences. Price per session; safety gear provided.',
                'd_ar' => 'جولات كارتينج لهواة رياضة المحركات، وهي من الأنشطة الاختيارية المدفوعة في المهرجان. السعر للجولة الواحدة، وتُوفَّر معدات السلامة.',
            ],
            [
                't' => 'Sohar Festival: Photography Competition Exhibition', 't_ar' => 'معرض مسابقة التصوير الضوئي في مهرجان صحار', 'cat' => 'arts', 'map' => 'grounds', 'venue' => $festivalVenue, 'org' => $gov,
                'start' => $festivalStart, 'end' => $festivalEnd, 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'photo-expo',
                'tags' => [['Photography', 'تصوير'], ['Exhibition', 'معرض']],
                'd' => 'Works from the festival\'s international photography competition, held with the International Federation of Photographic Art (FIAP). More than 200 photographers took part in the last edition.'.$included,
                'd_ar' => 'يعرض أعمال مسابقة المهرجان الدولية للتصوير الضوئي، التي تُنظَّم بالتعاون مع الاتحاد الدولي لفن التصوير الضوئي «فياب». وشارك في النسخة الماضية أكثر من 200 مصور،'.$includedAr,
            ],
            [
                't' => 'Sohar Festival: Kite Flying Day', 't_ar' => 'يوم الطائرات الورقية في مهرجان صحار', 'cat' => 'family', 'map' => 'grounds', 'venue' => $festivalVenue, 'org' => $gov,
                'start' => $at('2027-01-08 15:00'), 'end' => $at('2027-01-08 18:00'), 'price' => 0, 'cap' => 1000, 'feat' => false, 'seed' => 'kites',
                'tags' => [['Family', 'عائلي'], ['Kids', 'أطفال'], ['Outdoor', 'في الهواء الطلق']],
                'd' => 'An open family afternoon of kite flying on the festival grounds. Bring your own kite or make one at the activity tent. Free registration; festival entry applies.',
                'd_ar' => 'عصرية عائلية مفتوحة لإطلاق الطائرات الورقية في أرض المهرجان، فأحضر طائرتك الورقية أو اصنعها في خيمة الأنشطة. التسجيل مجاني، ويلزم الدخول بتذكرة المهرجان.',
            ],
            [
                't' => 'Oman International Rally', 't_ar' => 'رالي عُمان الدولي', 'cat' => 'sports', 'map' => 'grounds', 'venue' => ['Sohar Entertainment Centre (rally base), Sohar', 'مركز صحار الترفيهي (مقر الرالي)، صحار'], 'org' => ['Oman Automobile Association', 'الجمعية العمانية للسيارات'],
                'start' => $at('2027-01-21 16:00'), 'end' => $at('2027-01-23 18:00'), 'price' => 0, 'cap' => -1, 'feat' => true, 'seed' => 'oman-rally',
                'tags' => [['Motorsport', 'رياضة المحركات'], ['FIA MERC', 'بطولة الشرق الأوسط'], ['Free', 'مجاني']],
                'd' => 'Opening round of the FIA Middle East Rally Championship, based in Sohar since 2025. A ceremonial start and the short Sohar Super Special stage run at the Entertainment Centre on day one, followed by two days of gravel stages and a finish back in Sohar. Free for spectators.',
                'd_ar' => 'الجولة الافتتاحية لبطولة الشرق الأوسط للراليات التابعة للاتحاد الدولي للسيارات «فيا»، ومقرها صحار منذ عام 2025. يشهد اليوم الأول الانطلاقة الرسمية ومرحلة صحار الاستعراضية القصيرة في المركز الترفيهي، ثم تتواصل المراحل الترابية يومين، ويعود المتسابقون إلى صحار لخط النهاية. الحضور مجاني للجمهور.',
            ],
            [
                't' => 'Sohar Camel Festival', 't_ar' => 'مهرجان صحار للهجن', 'cat' => 'heritage', 'map' => 'camel_track', 'venue' => ['Sohar Camel Racetrack, Sohar', 'ميدان سباقات الهجن، صحار'], 'org' => ['Sohar Camel Festival Organising Committee', 'اللجنة المنظمة لمهرجان صحار للهجن'],
                'start' => $at('2027-01-21 06:30'), 'end' => $at('2027-01-23 11:00'), 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'camel-festival',
                'tags' => [['Camels', 'هجن'], ['Heritage', 'تراث'], ['Free', 'مجاني']],
                'd' => 'Three mornings of camel racing heats and a mazayna (camel beauty contest) with entries from across Oman. The 2026 edition drew around 600 camels.',
                'd_ar' => 'أشواط لسباقات الهجن على مدى ثلاثة صباحات، ومزاينة تشارك فيها هجن من أنحاء سلطنة عُمان. وشارك في نسخة 2026 نحو 600 من الهجن.',
            ],
            [
                't' => 'Al Khaburah Equestrian Festival', 't_ar' => 'مهرجان الفروسية بالخابورة', 'cat' => 'sports', 'map' => 'khaburah_club', 'venue' => ['Al Khaburah Equestrian Club, Al Breik, Al Khaburah', 'نادي الخابورة للفروسية، البريك، الخابورة'], 'org' => ['Al Khaburah Equestrian Club', 'نادي الخابورة للفروسية'],
                'start' => $at('2027-01-02 15:00'), 'end' => $at('2027-01-02 18:00'), 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'khaburah-horses',
                'tags' => [['Horses', 'خيل'], ['Heritage', 'تراث'], ['Free', 'مجاني']],
                'd' => 'Races for purebred Arabian and cross-bred horses, endurance heats and traditional horse displays, with riders from across North Al Batinah.',
                'd_ar' => 'أشواط للخيل العربية الأصيلة والمهجنة، وسباقات للقدرة والتحمل، واستعراضات تقليدية للخيل، بمشاركة فرسان من ولايات شمال الباطنة.',
            ],
            [
                't' => 'Tour of Oman: Stage Finish in Sohar', 't_ar' => 'وصول طواف عُمان إلى صحار', 'cat' => 'sports', 'map' => 'corniche', 'venue' => ['Sohar Corniche, Sohar', 'كورنيش صحار، صحار'], 'org' => ['Tour of Oman', 'طواف عُمان'],
                'start' => $at('2027-02-10 13:00'), 'end' => $at('2027-02-10 16:00'), 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'tour-of-oman',
                'tags' => [['Cycling', 'دراجات'], ['Free', 'مجاني']],
                'd' => 'Watch the professional peloton sprint to the line on the Sohar Corniche. In 2026 stage 4 rode up the Batinah coast through Al Suwaiq, Al Khaburah and Saham to finish in Sohar. The 2027 route is still to be confirmed. Expect partial road closures.',
                'd_ar' => 'تابع الدراجين المحترفين وهم يتسابقون نحو خط النهاية على كورنيش صحار. في عام 2026 سلكت المرحلة الرابعة ساحل الباطنة مرورًا بالسويق والخابورة وصحم حتى صحار، ولم يُعلن مسار 2027 بعد. ويُتوقع إغلاق جزئي لبعض الطرق.',
            ],
            [
                't' => 'Shinas Heritage Festival', 't_ar' => 'مهرجان شناص التراثي', 'cat' => 'heritage', 'map' => null, 'venue' => ['Shinas Camel Racetrack, Shinas', 'ميدان سباقات الهجن، شناص'], 'org' => ['Office of the Wali of Shinas', 'مكتب والي شناص'],
                'start' => $at('2027-02-14 07:00'), 'end' => $at('2027-02-17 21:00'), 'price' => 0, 'cap' => -1, 'feat' => true, 'seed' => 'shinas-heritage',
                'tags' => [['Camels', 'هجن'], ['Heritage', 'تراث'], ['Shinas', 'شناص'], ['Free', 'مجاني']],
                'd' => 'Four days of camel races and mazayna across age classes, with morning and evening sessions, a heritage village and a corner for home businesses. Organised by the Office of the Wali of Shinas with the Oman Camel Racing Federation.',
                'd_ar' => 'أربعة أيام من سباقات الهجن والمزاينة لمختلف الفئات العمرية، على فترتين صباحية ومسائية، مع قرية تراثية وركن للأسر المنتجة. ينظمه مكتب والي شناص بالتعاون مع الاتحاد العماني لسباقات الهجن.',
            ],
            [
                't' => 'North Al Batinah Camel Race Festival', 't_ar' => 'مهرجان محافظة شمال الباطنة لسباق الهجن', 'cat' => 'sports', 'map' => 'camel_track', 'venue' => ['Sohar Camel Racetrack, Sohar', 'ميدان سباقات الهجن، صحار'], 'org' => ['Sohar Camel Racing Committee', 'لجنة سباقات الهجن بولاية صحار'],
                'start' => $at('2027-04-10 06:30'), 'end' => $at('2027-04-10 11:00'), 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'nb-camel-race',
                'tags' => [['Camels', 'هجن'], ['Free', 'مجاني']],
                'd' => 'The governorate\'s camel race festival, with 17 heats for the Fitameen, Hajayej and Laqaya age classes in the 2026 edition.',
                'd_ar' => 'مهرجان المحافظة لسباقات الهجن، وضمت نسخة 2026 منه 17 شوطًا لفئات الفطامين والحجايج واللقايا.',
            ],
            [
                't' => 'Sohar University Theatre Festival', 't_ar' => 'مهرجان جامعة صحار المسرحي', 'cat' => 'arts', 'map' => 'university', 'venue' => ['University Theatre, Sohar University', 'مسرح الجامعة، جامعة صحار'], 'org' => ['Sohar University', 'جامعة صحار'],
                'start' => $at('2027-04-04 19:00'), 'end' => $at('2027-04-07 22:00'), 'price' => 0, 'cap' => 400, 'feat' => false, 'seed' => 'su-theatre',
                'tags' => [['Theatre', 'مسرح'], ['Students', 'طلاب']],
                'd' => 'An annual student theatre festival with one play a night by higher-education troupes, each followed by a critique session. The 14th edition ran 5–8 April 2026 during the university\'s Student Week.',
                'd_ar' => 'مهرجان مسرحي طلابي سنوي يُقدَّم فيه عرض كل ليلة لفرق من مؤسسات التعليم العالي، تليه جلسة نقدية. أُقيمت النسخة الرابعة عشرة من 5 إلى 8 أبريل 2026 ضمن الأسبوع الطلابي.',
            ],
            [
                't' => (56 + $shift).'th National Day', 't_ar' => 'العيد الوطني الـ'.(56 + $shift).' المجيد', 'cat' => 'national', 'map' => null, 'venue' => ['All wilayats of North Al Batinah', 'جميع ولايات محافظة شمال الباطنة'], 'org' => ['North Al Batinah Governorate', 'محافظة شمال الباطنة'],
                'start' => $at('2026-11-20 00:00'), 'end' => $at('2026-11-21 23:59'), 'price' => 0, 'cap' => -1, 'feat' => true, 'seed' => 'national-day',
                'tags' => [['National Day', 'العيد الوطني'], ['Free', 'مجاني']],
                'd' => 'Oman celebrates National Day on 20 and 21 November (Royal Decree 15/2025). Flags and lights go up across the wilayats; local programmes are announced by the wilayat offices.',
                'd_ar' => 'تحتفل سلطنة عُمان بالعيد الوطني يومي 20 و21 نوفمبر بموجب المرسوم السلطاني رقم 15/2025، فتتزين الولايات بالأعلام والأنوار، وتعلن مكاتب الولاة برامج الاحتفال في كل ولاية.',
            ],
            [
                't' => 'Al Khaburah Eid Habta', 't_ar' => 'هبطة الخابورة', 'cat' => 'markets', 'map' => null, 'venue' => ['Habta ground, Al Khaburah', 'ميدان الهبطة، الخابورة'], 'org' => ['Wilayat of Al Khaburah', 'ولاية الخابورة'],
                'start' => $at('2027-05-12 06:00'), 'end' => $at('2027-05-12 12:00'), 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'khaburah-habta',
                'tags' => [['Souq', 'سوق'], ['Eid', 'العيد'], ['Heritage', 'تراث']],
                'd' => 'The traditional pre-Eid al-Adha market: a livestock auction plus corners for home businesses (ghee, halwa, spices, handicrafts), birds and mishkak skewers. The date follows the Hijri calendar.',
                'd_ar' => 'السوق التقليدي الذي يسبق عيد الأضحى، وفيه مزاد للمواشي وأركان للأسر المنتجة تبيع السمن البلدي والحلوى والبهارات والمشغولات اليدوية، إلى جانب الطيور وأعواد المشاكيك. ويتحدد الموعد بالتقويم الهجري.',
            ],
            [
                't' => 'Liwa Eid Nights', 't_ar' => 'عيود لوى', 'cat' => 'heritage', 'map' => 'liwa_fort', 'venue' => ['Liwa', 'ولاية لوى'], 'org' => ['Liwa Municipal Affairs Committee', 'لجنة الشؤون البلدية بولاية لوى'],
                'start' => $at('2027-05-16 20:00'), 'end' => $at('2027-05-18 23:30'), 'price' => 0, 'cap' => -1, 'feat' => false, 'seed' => 'liwa-eid',
                'tags' => [['Eid', 'العيد'], ['Folk arts', 'فنون شعبية'], ['Family', 'عائلي']],
                'd' => 'Three community evenings over Eid al-Adha with competitions, folk-art performances and poetry, attended by families from Liwa, Shinas and Sohar. Dates follow the Hijri calendar.',
                'd_ar' => 'ثلاث أمسيات مجتمعية في عيد الأضحى، فيها مسابقات وعروض للفنون الشعبية وقراءات شعرية، يحضرها أهالي لوى وشناص وصحار. ويتحدد الموعد بالتقويم الهجري.',
            ],
        ];

        // ------------------------------------------------------------------
        // Food stalls at the festival food court (generic names, not real
        // businesses; no phone numbers or websites).
        // ------------------------------------------------------------------
        $stallHours = [
            ['day' => 'Daily (festival season)', 'day_ar' => 'يوميًا طوال موسم المهرجان', 'opening' => '16:00', 'closing' => '23:59'],
        ];
        $restaurantDefs = [
            ['n' => 'Sohar Karak House', 'n_ar' => 'بيت كرك صحار', 'cui' => 'Café', 'cui_ar' => 'مقهى', 'feat' => true, 'seed' => 'karak-house', 'dx' => 0.0000, 'dy' => 0.0003,
                'd' => 'Karak tea, Omani kahwa and fresh chapati at the festival food court.', 'd_ar' => 'شاي الكرك والقهوة العمانية والجباتي الطازج في ساحة المطاعم بالمهرجان.'],
            ['n' => 'Batinah Mishkak Grill', 'n_ar' => 'مشاكيك الباطنة', 'cui' => 'Omani grill', 'cui_ar' => 'مشاوي عمانية', 'feat' => true, 'seed' => 'mishkak', 'dx' => 0.0003, 'dy' => 0.0001,
                'd' => 'Charcoal-grilled mishkak skewers with tamarind sauce.', 'd_ar' => 'أسياخ المشاكيك المشوية على الفحم مع صلصة التمر الهندي.'],
            ['n' => 'Omani Halwa & Kahwa Corner', 'n_ar' => 'ركن الحلوى والقهوة العمانية', 'cui' => 'Omani sweets', 'cui_ar' => 'حلويات عمانية', 'feat' => false, 'seed' => 'halwa', 'dx' => -0.0002, 'dy' => 0.0002,
                'd' => 'Omani halwa, dates and kahwa served the traditional way.', 'd_ar' => 'الحلوى العمانية والتمر والقهوة، تُقدَّم على الطريقة التقليدية.'],
            ['n' => 'Omani Village Home Kitchen', 'n_ar' => 'مطبخ القرية العمانية', 'cui' => 'Omani home cooking', 'cui_ar' => 'أكلات عمانية منزلية', 'feat' => false, 'seed' => 'home-kitchen', 'dx' => -0.0004, 'dy' => -0.0001,
                'd' => 'Harees, arsiya and other home-cooked dishes from local home businesses.', 'd_ar' => 'الهريس والعرسية وأطباق منزلية أخرى من الأسر المنتجة.'],
            ['n' => 'Batinah Coast Seafood Stall', 'n_ar' => 'ركن مأكولات ساحل الباطنة البحرية', 'cui' => 'Seafood', 'cui_ar' => 'مأكولات بحرية', 'feat' => false, 'seed' => 'seafood', 'dx' => 0.0004, 'dy' => -0.0002,
                'd' => 'Fried and grilled fish and shrimp, Batinah coast style.', 'd_ar' => 'سمك وروبيان مقلي ومشوي على طريقة ساحل الباطنة.'],
            ['n' => 'Oasis Fresh Juice', 'n_ar' => 'عصائر الواحة', 'cui' => 'Juices', 'cui_ar' => 'عصائر', 'feat' => false, 'seed' => 'juice', 'dx' => 0.0001, 'dy' => -0.0004,
                'd' => 'Fresh juices, lemon-mint and cold drinks.', 'd_ar' => 'عصائر طازجة وليمون بالنعناع ومشروبات باردة.'],
        ];
        // ------------------------------------------------------------------
        // Unified locations table (services, emergency, parking, first aid)
        // ------------------------------------------------------------------
        $locationDefs = [
            ['type' => 'emergency', 'name' => 'Royal Oman Police (Emergency)', 'name_ar' => 'طوارئ شرطة عُمان السلطانية', 'phone' => '9999', 'lat' => 24.34061, 'lng' => 56.69853, 'addr' => 'Sohar Police Station, Al Muwaylah St', 'addr_ar' => 'مركز شرطة صحار، شارع المويلح', '24h' => true],
            ['type' => 'emergency', 'name' => 'Sohar Hospital Emergency', 'name_ar' => 'طوارئ مستشفى صحار', 'phone' => '9999', 'lat' => 24.32590, 'lng' => 56.67817, 'addr' => 'Sohar Hospital, Al Muwaylah', 'addr_ar' => 'مستشفى صحار، المويلح', '24h' => true],
            ['type' => 'first_aid', 'name' => 'Festival First Aid Point', 'name_ar' => 'نقطة الإسعافات الأولية بالمهرجان', 'phone' => null, 'lat' => $g['lat'] + 0.0009, 'lng' => $g['lng'] + 0.0011, 'addr' => 'Sohar Festival grounds', 'addr_ar' => 'أرض مهرجان صحار', '24h' => false],
            ['type' => 'parking', 'name' => 'Sohar Festival Parking', 'name_ar' => 'مواقف مهرجان صحار', 'phone' => null, 'lat' => $g['lat'] + 0.0016, 'lng' => $g['lng'] - 0.0012, 'addr' => 'Sohar Entertainment Centre', 'addr_ar' => 'مركز صحار الترفيهي', '24h' => false],
            ['type' => 'service', 'name' => 'Lost & Found', 'name_ar' => 'المفقودات', 'phone' => null, 'lat' => $g['lat'] + 0.0010, 'lng' => $g['lng'] - 0.0002, 'addr' => 'Festival Information Desk', 'addr_ar' => 'مكتب معلومات المهرجان', '24h' => false],
        ];
        // ------------------------------------------------------------------
        // Announcements
        // ------------------------------------------------------------------
        $festivalDates = $festivalStart->format('j M').' – '.$festivalEnd->format('j M Y');
        $announcementDefs = [
            ['t' => 'Welcome to North Batinah Events', 't_ar' => 'مرحبًا بكم في فعاليات شمال الباطنة',
                'c' => 'Festivals, races and heritage events across Sohar, Shinas, Liwa, Saham, Al Khaburah and Al Suwaiq in one app.',
                'c_ar' => 'يجمع لكم هذا التطبيق المهرجانات والسباقات والفعاليات التراثية في صحار وشناص ولوى وصحم والخابورة والسويق.',
                'type' => 'info', 'prio' => 2, 'pin' => true, 'end' => $festivalEnd],
            ['t' => 'Sohar Festival tickets are in the app', 't_ar' => 'تذاكر مهرجان صحار متاحة في التطبيق',
                'c' => "Sohar Festival returns to the Sohar Entertainment Centre ({$festivalDates}, to be confirmed by the organising committee). Entry 0.500 OMR per day; pay with your wallet at the gate and the stalls.",
                'c_ar' => 'يعود مهرجان صحار إلى مركز صحار الترفيهي من '.self::arDate($festivalStart, false).' إلى '.self::arDate($festivalEnd).'، والمواعيد بانتظار تأكيد اللجنة المنظمة. رسوم الدخول OMR 0.500 لليوم، ويمكنك الدفع بمحفظتك عند البوابة وفي الأكشاك.',
                'type' => 'celebration', 'prio' => 3, 'pin' => true, 'end' => $festivalEnd],
            ['t' => 'Festival gate times', 't_ar' => 'مواعيد بوابات المهرجان',
                'c' => 'Expected daily hours during the festival: 4:00 pm – midnight. Final timings will be announced here.',
                'c_ar' => 'يُتوقع أن تعمل البوابات يوميًا خلال المهرجان من 16:00 حتى منتصف الليل، وسنعلن المواعيد النهائية هنا.',
                'type' => 'info', 'prio' => 2, 'pin' => false, 'end' => $festivalEnd],
            ['t' => 'Parking at the festival', 't_ar' => 'مواقف السيارات في المهرجان',
                'c' => 'Use the marked car parks around the Sohar Entertainment Centre and arrive early on weekends and holidays.',
                'c_ar' => 'استخدم المواقف المخصصة حول مركز صحار الترفيهي، واحرص على الحضور مبكرًا في عطلات نهاية الأسبوع والإجازات.',
                'type' => 'info', 'prio' => 1, 'pin' => false, 'end' => $festivalEnd],
            ['t' => 'School holiday family programme', 't_ar' => 'برنامج العائلة في إجازة المدارس',
                'c' => 'The Child & Family Theatre, carnival shows and Omani Village run daily for families during the festival.',
                'c_ar' => 'تُقدَّم للعائلات كل يوم طوال المهرجان عروض مسرح الطفل والأسرة والعروض الكرنفالية وأنشطة القرية العمانية.',
                'type' => 'celebration', 'prio' => 1, 'pin' => false, 'end' => $festivalEnd],
            ['t' => 'National Day: 20–21 November', 't_ar' => 'العيد الوطني يومي 20 و21 نوفمبر',
                'c' => 'Oman celebrates National Day on 20 and 21 November. Watch this space for celebrations across the wilayats.',
                'c_ar' => 'تحتفل سلطنة عُمان بالعيد الوطني يومي 20 و21 نوفمبر، وسننشر هنا برامج الاحتفال في ولايات المحافظة.',
                'type' => 'celebration', 'prio' => 2, 'pin' => false, 'end' => $at('2026-11-22 23:59')],
            ['t' => 'Road closures on race days', 't_ar' => 'إغلاق الطرق أيام السباقات',
                'c' => 'Expect partial road closures in Sohar during the rally and cycling stages. Follow Royal Oman Police announcements.',
                'c_ar' => 'يُتوقع إغلاق جزئي لبعض الطرق في صحار خلال الرالي ومراحل سباق الدراجات. تابعوا إعلانات شرطة عُمان السلطانية.',
                'type' => 'warning', 'prio' => 1, 'pin' => false, 'end' => $at('2027-02-11 23:59')],
        ];
        $nationalDay = (56 + $shift).'th National Day';
        $notificationDefs = [
            ['t' => 'Welcome to North Batinah Events!', 't_ar' => 'مرحبًا بك في فعاليات شمال الباطنة',
                'b' => 'Browse festivals and races across the governorate, book tickets and pay with your wallet.',
                'b_ar' => 'تصفّح المهرجانات والسباقات في المحافظة واحجز التذاكر وادفع بمحفظتك.', 'type' => 'info', 'read' => true],
            ['t' => 'Tickets confirmed', 't_ar' => 'أُكّدت تذاكرك',
                'b' => "Your 2 tickets for {$festivalTitle} ({$festivalStart->format('j M Y')}) are ready. Show the QR code at the gate.",
                'b_ar' => 'تذكرتاك لمهرجان صحار يوم '.self::arDate($festivalStart).' جاهزتان، فأظهر رمز QR عند البوابة.', 'type' => 'ticket', 'read' => false],
            ['t' => 'Event reminder', 't_ar' => 'تذكير بالفعالية',
                'b' => "{$nationalDay} is on 20–21 November. See what's on across North Al Batinah.",
                'b_ar' => 'يحل العيد الوطني الـ'.(56 + $shift).' يومي 20 و21 نوفمبر، فتعرف على برامج الاحتفال في ولايات شمال الباطنة.', 'type' => 'event', 'read' => false],
            ['t' => 'Payment received', 't_ar' => 'استُلمت دفعتك',
                'b' => 'We received your payment of 1.000 OMR for 2 Sohar Festival tickets. Thank you!',
                'b_ar' => 'استلمنا مبلغ OMR 1.000 ثمن تذكرتين لمهرجان صحار، شكرًا لك.', 'type' => 'payment', 'read' => true],
        ];

        return [
            'now' => $now,
            'season' => $season,
            'festival_title' => $festivalTitle,
            'festival_start' => $festivalStart,
            'grounds' => $g,
            'categories' => $categoryDefs,
            'map' => $mapDefs,
            'events' => $eventDefs,
            'stall_hours' => $stallHours,
            'restaurants' => $restaurantDefs,
            'restaurant_location' => ['Food court, Sohar Festival', 'ساحة المطاعم في مهرجان صحار'],
            'locations' => $locationDefs,
            'announcements' => $announcementDefs,
            'notifications' => $notificationDefs,
            'demo_visitor_phone' => '96891234567',
        ];
    }

    /** "22 ديسمبر 2026" (Western digits, Gregorian month names used in Oman). */
    private static function arDate(Carbon $date, bool $withYear = true): string
    {
        $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

        return $date->day.' '.$months[$date->month - 1].($withYear ? ' '.$date->year : '');
    }

    public function run(): void
    {
        $tenantId = app(TenantContext::class)->id();
        [
            'now' => $now, 'festival_title' => $festivalTitle, 'festival_start' => $festivalStart, 'grounds' => $g,
            'categories' => $categoryDefs, 'map' => $mapDefs, 'events' => $eventDefs, 'stall_hours' => $stallHours,
            'restaurants' => $restaurantDefs, 'restaurant_location' => $restaurantLocation, 'locations' => $locationDefs,
            'announcements' => $announcementDefs, 'notifications' => $notificationDefs,
        ] = self::content();

        $categories = [];
        foreach ($categoryDefs as $i => $c) {
            $categories[$c['key']] = Category::updateOrCreate(['name' => $c['name']], [
                'name_ar' => $c['name_ar'],
                'description' => $c['d'],
                'description_ar' => $c['d_ar'],
                'icon_name' => $c['icon'],
                'color_value' => $c['color'],
                'image_url' => self::img('nb-cat-'.$c['key']),
                'display_order' => $i + 1,
                'is_active' => true,
            ]);
        }

        $map = [];
        foreach ($mapDefs as $m) {
            $map[$m['key']] = MapLocation::updateOrCreate(['name' => $m['n']], [
                'type' => $m['type'],
                'name_ar' => $m['n_ar'],
                'description' => $m['d'],
                'description_ar' => $m['d_ar'],
                'latitude' => round($m['lat'], 6),
                'longitude' => round($m['lng'], 6),
                'icon' => $m['icon'],
                'color' => $m['color'],
                'is_active' => true,
            ])->id;
        }

        foreach ($eventDefs as $e) {
            $event = Event::firstOrCreate(['title' => $e['t']], [
                'title_ar' => $e['t_ar'],
                'description' => $e['d'],
                'description_ar' => $e['d_ar'],
                'category_id' => $categories[$e['cat']]->id,
                'start_time' => $e['start'],
                'end_time' => $e['end'],
                'location' => $e['venue'][0],
                'location_ar' => $e['venue'][1],
                'map_location_id' => $e['map'] ? $map[$e['map']] : null,
                'image_url' => self::img($e['seed']),
                'images' => [self::img($e['seed'].'-a'), self::img($e['seed'].'-b'), self::img($e['seed'].'-c')],
                'price' => $e['price'],
                'currency' => 'OMR',
                'available_tickets' => $e['cap'],
                'total_tickets' => $e['cap'],
                'organizer_name' => $e['org'][0],
                'organizer_name_ar' => $e['org'][1],
                'is_featured' => $e['feat'],
                'is_active' => true,
                'pricing' => [[
                    'type' => 'standard', 'price' => $e['price'], 'quantity' => $e['cap'],
                    'benefits' => self::PRICING_BENEFITS[$e['price'] > 0 ? 'paid' : 'free'][0],
                    'benefits_ar' => self::PRICING_BENEFITS[$e['price'] > 0 ? 'paid' : 'free'][1],
                ]],
            ]);
            foreach ($e['tags'] as [$tag, $tagAr]) {
                EventTag::firstOrCreate(['event_id' => $event->id, 'tag' => $tag], ['tag_ar' => $tagAr]);
            }
        }

        $foodLat = $g['lat'] - 0.0002;
        $foodLng = $g['lng'] + 0.0013;
        foreach ($restaurantDefs as $r) {
            Restaurant::firstOrCreate(['name' => $r['n']], [
                'name_ar' => $r['n_ar'],
                'description' => $r['d'],
                'description_ar' => $r['d_ar'],
                'cuisine' => $r['cui'],
                'cuisine_ar' => $r['cui_ar'],
                'location' => $restaurantLocation[0],
                'location_ar' => $restaurantLocation[1],
                'latitude' => round($foodLat + $r['dy'], 6),
                'longitude' => round($foodLng + $r['dx'], 6),
                'rating' => 0,
                'total_ratings' => 0,
                'price_range' => '$',
                'image_url' => self::img($r['seed']),
                'images' => [self::img($r['seed'].'-a'), self::img($r['seed'].'-b')],
                'phone' => null,
                'website' => null,
                'features' => ['Family seating', 'Wallet payment', 'Takeaway'],
                'opening_hours' => $stallHours,
                'menu_url' => null,
                'is_open' => true,
                'has_delivery' => false,
                'has_parking' => true,
                'has_wifi' => false,
                'is_featured' => $r['feat'],
                'is_active' => true,
            ]);
        }

        $locationsHaveTenant = Schema::hasColumn('locations', 'tenant_id');
        foreach ($locationDefs as $l) {
            $values = [
                'name_ar' => $l['name_ar'],
                'type' => $l['type'],
                'description' => $l['name'],
                'description_ar' => $l['name_ar'],
                'latitude' => round($l['lat'], 6),
                'longitude' => round($l['lng'], 6),
                'address' => $l['addr'],
                'address_ar' => $l['addr_ar'],
                'contact_number' => $l['phone'],
                'additional_info' => json_encode(['is_24_hours' => $l['24h']]),
                'is_active' => true,
                'updated_at' => now(),
            ];
            $key = ['name' => $l['name']] + ($locationsHaveTenant ? ['tenant_id' => $tenantId] : []);
            if (DB::table('locations')->where($key)->exists()) {
                DB::table('locations')->where($key)->update($values);
            } else {
                DB::table('locations')->insert($key + $values + ['created_at' => now()]);
            }
        }

        $author = User::orderBy('id')->value('id');
        foreach ($announcementDefs as $a) {
            Announcement::firstOrCreate(['title' => $a['t']], [
                'title_ar' => $a['t_ar'],
                'content' => $a['c'],
                'content_ar' => $a['c_ar'],
                'type' => $a['type'],
                'priority' => $a['prio'],
                'is_pinned' => $a['pin'],
                'is_active' => true,
                'start_datetime' => $now,
                'end_datetime' => $a['end'],
                'created_by' => $author,
            ]);
        }

        // ------------------------------------------------------------------
        // Demo visitor with personal data (Sohar Festival tickets, favorites,
        // notifications). Log in with phone 91234567 (stored as 96891234567)
        // + test OTP.
        // ------------------------------------------------------------------
        $demo = User::firstOrCreate(
            ['phone_number' => '96891234567'],
            [
                'name' => 'أحمد الزدجالي',
                'email' => 'visitor@northbatinah.events',
                'password' => Hash::make('visitor123'),
                'phone_verified_at' => now(),
                'preferred_language' => 'ar',
            ]
        );

        $soharFestival = Event::where('title', $festivalTitle)->first();
        $featuredRestaurant = Restaurant::where('name', 'Sohar Karak House')->first();
        $bookingDate = $festivalStart->format('Y-m-d');

        $hasSeedOrder = $soharFestival && Payment::where('user_id', $demo->id)
            ->where('payable_id', $soharFestival->id)->where('payment_type', 'ticket')->exists();
        if ($soharFestival && ! $hasSeedOrder) {
            $txn = 'TXN'.strtoupper(Str::random(10));
            Payment::create([
                'user_id' => $demo->id,
                'transaction_id' => $txn,
                'payment_type' => 'ticket',
                'method' => 'gateway',
                'payable_id' => $soharFestival->id,
                'payable_type' => Event::class,
                'amount' => (float) $soharFestival->price * 2,
                'amount_minor' => $soharFestival->unitPriceMinor() * 2,
                'fulfilled_at' => now(),
                'currency' => 'OMR',
                'status' => 'completed',
                'payment_method' => 'card',
                'paid_at' => now(),
                'metadata' => ['event_id' => $soharFestival->id, 'quantity' => 2, 'ticket_type' => 'standard', 'booking_date' => $bookingDate, 'tickets_minor' => $soharFestival->unitPriceMinor() * 2],
            ]);
            for ($i = 0; $i < 2; $i++) {
                Ticket::create([
                    'user_id' => $demo->id,
                    'event_id' => $soharFestival->id,
                    'transaction_id' => $txn,
                    'booking_date' => $bookingDate,
                    'max_check_ins' => 1,
                    'quantity' => 1,
                    'ticket_type' => 'standard',
                    'status' => 'active',
                    'price' => (float) $soharFestival->price,
                    'currency' => 'OMR',
                    'holder_name' => $demo->name,
                    'purchase_date' => now(),
                ]);
            }
        }
        if ($soharFestival) {
            UserFavorite::firstOrCreate([
                'user_id' => $demo->id,
                'favoritable_type' => Event::class,
                'favoritable_id' => $soharFestival->id,
            ]);
        }
        if ($featuredRestaurant) {
            UserFavorite::firstOrCreate([
                'user_id' => $demo->id,
                'favoritable_type' => Restaurant::class,
                'favoritable_id' => $featuredRestaurant->id,
            ]);
        }

        foreach ($notificationDefs as $n) {
            Notification::firstOrCreate(['user_id' => $demo->id, 'title' => $n['t']], [
                'title_ar' => $n['t_ar'],
                'body' => $n['b'],
                'body_ar' => $n['b_ar'],
                'type' => $n['type'],
                'data' => null,
                'is_read' => $n['read'],
                'read_at' => $n['read'] ? now() : null,
            ]);
        }

        // Branding / theme / modules live in the tenant settings (TenantSeeder).

        echo 'North Batinah Events data seeded: '
            .Category::count().' categories, '
            .Event::count().' events, '
            .Restaurant::count().' food stalls, '
            .MapLocation::count().' map locations, '
            .Notification::count()." notifications.\n";
    }
}
