<?php

namespace Database\Seeders;

use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Event;
use App\Models\MapLocation;
use App\Models\Place;
use App\Models\PlaceCategory;
use App\Support\HtmlSanitizer;
use App\Support\MediaPresenter;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Places to visit, content collections and pages of the seeded tenants
 * (docs/places_content_contract.md). Runs inside the current tenant.
 *
 * north-batinah: REAL places only. Coordinates and every fact come from
 * docs/north_batinah_seed_sources.md (OpenStreetMap / cited articles);
 * opening hours, fees, phone numbers and links are unknown and stay null
 * (the organizer fills them in the admin). Only Sohar Fort has a photo
 * (the bundled onboarding image of the fort); the app shows icon tiles for
 * the others. The partners collection is created EMPTY on purpose.
 *
 * demo: fictional places around the demo venue and fictional brands.
 *
 * Idempotent: rows are matched by natural key (category/collection key,
 * place/page slug, item slug within its collection) and never overwritten,
 * so edits made in the admin survive a re-seed. Reworded Arabic texts reach
 * existing rows through `php artisan content:sync-arabic`.
 */
class PlacesContentSeeder extends Seeder
{
    public static function definitions(string $tenantSlug): array
    {
        return match ($tenantSlug) {
            'north-batinah' => self::northBatinah(),
            'demo' => self::demo(),
            default => ['categories' => [], 'places' => [], 'collections' => [], 'pages' => []],
        };
    }

    private static function p(string ...$paragraphs): string
    {
        return implode('', array_map(fn ($p) => '<p>'.$p.'</p>', $paragraphs));
    }

    public static function northBatinah(): array
    {
        $w = [
            'sohar' => ['en' => 'Sohar', 'ar' => 'صحار'],
            'liwa' => ['en' => 'Liwa', 'ar' => 'لوى'],
            'shinas' => ['en' => 'Shinas', 'ar' => 'شناص'],
            'saham' => ['en' => 'Saham', 'ar' => 'صحم'],
            'khaburah' => ['en' => 'Al Khaburah', 'ar' => 'الخابورة'],
            'suwaiq' => ['en' => 'Al Suwaiq', 'ar' => 'السويق'],
        ];
        $addr = fn (string $en, string $ar) => ['en' => $en.', North Al Batinah', 'ar' => $ar.'، محافظة شمال الباطنة'];

        // Map pins (map_locations, English name) whose events are held at a place.
        $grounds = ['Sohar Entertainment Centre (Sohar Festival grounds)', 'Sohar Festival Main Stage', 'Omani Village',
            'Child & Family Theatre', 'Festival Karting Track', 'Festival Food Court'];

        return [
            'categories' => [
                ['key' => 'forts', 'name' => ['en' => 'Forts & castles', 'ar' => 'قلاع وحصون'], 'icon' => 'fort', 'color' => '#8D5B3A'],
                ['key' => 'beaches', 'name' => ['en' => 'Beaches & corniche', 'ar' => 'شواطئ وكورنيش'], 'icon' => 'beach_access', 'color' => '#0288D1'],
                ['key' => 'souqs', 'name' => ['en' => 'Souqs & markets', 'ar' => 'أسواق'], 'icon' => 'storefront', 'color' => '#C2185B'],
                ['key' => 'parks', 'name' => ['en' => 'Parks & gardens', 'ar' => 'حدائق ومتنزهات'], 'icon' => 'park', 'color' => '#2E7D32'],
                ['key' => 'museums', 'name' => ['en' => 'Museums & landmarks', 'ar' => 'متاحف ومعالم'], 'icon' => 'museum', 'color' => '#6A1B9A'],
                ['key' => 'mosques', 'name' => ['en' => 'Mosques', 'ar' => 'مساجد'], 'icon' => 'mosque', 'color' => '#00796B'],
                ['key' => 'venues', 'name' => ['en' => 'Event venues', 'ar' => 'مواقع الفعاليات'], 'icon' => 'stadium', 'color' => '#E0533D'],
            ],
            'places' => [
                [
                    'slug' => 'sohar-fort', 'category' => 'forts', 'lat' => 24.36203, 'lng' => 56.74929, 'wilayat' => $w['sohar'], 'featured' => true,
                    'name' => ['en' => 'Sohar Fort', 'ar' => 'قلعة صحار'],
                    'summary' => ['en' => 'Historic fort in the city of Sohar, a short walk from Sohar Souq.', 'ar' => 'قلعة تاريخية في مدينة صحار، على مسافة قصيرة من سوق صحار.'],
                    'description' => [
                        'en' => self::p('Sohar Fort is a historic fort in the city of Sohar, about 400 metres from Sohar Souq.'),
                        'ar' => self::p('قلعة صحار قلعة تاريخية في مدينة صحار، وتبعد نحو 400 متر عن سوق صحار.'),
                    ],
                    'address' => $addr('Sohar', 'صحار'),
                    'cover' => 'tenants/north-batinah/onboarding_1.jpg',
                    'map_pins' => [],
                ],
                [
                    'slug' => 'sohar-corniche', 'category' => 'beaches', 'lat' => 24.37345, 'lng' => 56.74276, 'wilayat' => $w['sohar'], 'featured' => true,
                    'name' => ['en' => 'Sohar Corniche', 'ar' => 'كورنيش صحار'],
                    'summary' => ['en' => 'Sohar\'s seafront along Al Bahri Road.', 'ar' => 'الواجهة البحرية لمدينة صحار على امتداد الطريق البحري.'],
                    'description' => [
                        'en' => self::p('The corniche runs along Al Bahri Road on the Sohar seafront.', 'Stage 4 of the 2026 Tour of Oman finished on Sohar Beach Corniche on 10 February 2026.'),
                        'ar' => self::p('يمتد الكورنيش على الطريق البحري في واجهة صحار البحرية.', 'وعلى كورنيش شاطئ صحار انتهت المرحلة الرابعة من طواف عُمان في 10 فبراير 2026.'),
                    ],
                    'address' => ['en' => 'Al Bahri Road, Sohar', 'ar' => 'الطريق البحري، صحار'],
                    'map_pins' => ['Sohar Corniche'],
                ],
                [
                    'slug' => 'sohar-souq', 'category' => 'souqs', 'lat' => 24.36552, 'lng' => 56.74795, 'wilayat' => $w['sohar'], 'featured' => true,
                    'name' => ['en' => 'Sohar Souq', 'ar' => 'سوق صحار'],
                    'summary' => ['en' => 'Market in the city of Sohar, near Sohar Fort.', 'ar' => 'سوق في مدينة صحار قرب قلعة صحار.'],
                    'description' => [
                        'en' => self::p('Sohar Souq is a market in the city of Sohar, about 400 metres from Sohar Fort.'),
                        'ar' => self::p('سوق صحار سوق في مدينة صحار، ويبعد نحو 400 متر عن قلعة صحار.'),
                    ],
                    'address' => $addr('Sohar', 'صحار'),
                    'map_pins' => [],
                ],
                [
                    'slug' => 'liwa-fort', 'category' => 'forts', 'lat' => 24.52149, 'lng' => 56.57506, 'wilayat' => $w['liwa'], 'featured' => true,
                    'name' => ['en' => 'Liwa Fort', 'ar' => 'حصن لوى'],
                    'summary' => ['en' => 'Fort in the Wilayat of Liwa.', 'ar' => 'حصن في ولاية لوى.'],
                    'address' => $addr('Liwa', 'لوى'),
                    'map_pins' => [],
                ],
                [
                    'slug' => 'shinas-fort', 'category' => 'forts', 'lat' => 24.74421, 'lng' => 56.46637, 'wilayat' => $w['shinas'],
                    'name' => ['en' => 'Shinas Fort', 'ar' => 'قلعة شناص'],
                    'summary' => ['en' => 'Fort in the Wilayat of Shinas.', 'ar' => 'قلعة في ولاية شناص.'],
                    'address' => $addr('Shinas', 'شناص'),
                    'map_pins' => [],
                ],
                [
                    'slug' => 'saham-fort', 'category' => 'forts', 'lat' => 24.17260, 'lng' => 56.89041, 'wilayat' => $w['saham'],
                    'name' => ['en' => 'Saham Fort', 'ar' => 'حصن صحم'],
                    'summary' => ['en' => 'Fort in the Wilayat of Saham.', 'ar' => 'حصن في ولاية صحم.'],
                    'address' => $addr('Saham', 'صحم'),
                    'map_pins' => [],
                ],
                [
                    'slug' => 'al-khaburah-fort', 'category' => 'forts', 'lat' => 23.98225, 'lng' => 57.10559, 'wilayat' => $w['khaburah'],
                    'name' => ['en' => 'Al Khaburah Fort', 'ar' => 'حصن الخابورة'],
                    'summary' => ['en' => 'Fort in the Wilayat of Al Khaburah.', 'ar' => 'حصن في ولاية الخابورة.'],
                    'description' => [
                        'en' => self::p('Al Khaburah Fort stands in the Wilayat of Al Khaburah. On 6 January 2025 it hosted a classic car gathering.'),
                        'ar' => self::p('يقع حصن الخابورة في ولاية الخابورة، واستضاف في 6 يناير 2025 تجمعًا للسيارات الكلاسيكية.'),
                    ],
                    'address' => $addr('Al Khaburah', 'الخابورة'),
                    'map_pins' => [],
                ],
                [
                    'slug' => 'al-suwaiq-fort', 'category' => 'forts', 'lat' => 23.84915, 'lng' => 57.43970, 'wilayat' => $w['suwaiq'],
                    'name' => ['en' => 'Al Suwaiq Fort', 'ar' => 'حصن السويق'],
                    'summary' => ['en' => 'Fort in the Wilayat of Al Suwaiq.', 'ar' => 'حصن في ولاية السويق.'],
                    'address' => $addr('Al Suwaiq', 'السويق'),
                    'map_pins' => [],
                ],
                [
                    'slug' => 'sohar-entertainment-centre', 'category' => 'venues', 'lat' => 24.33927, 'lng' => 56.68458, 'wilayat' => $w['sohar'], 'featured' => true,
                    'name' => ['en' => 'Sohar Entertainment Centre', 'ar' => 'مركز صحار الترفيهي'],
                    'summary' => ['en' => 'Home of the Sohar Festival and base of the Oman International Rally in Sohar.', 'ar' => 'مقر مهرجان صحار ومركز رالي عُمان الدولي في صحار.'],
                    'description' => [
                        'en' => self::p('The Sohar Festival is held at the Sohar Entertainment Centre. The 4th edition ran from 22 December 2025 to 31 January 2026 and welcomed about 507,000 visitors.', 'The centre was also the base of the Oman International Rally in January 2026, with the finish there. The map pin is approximate.'),
                        'ar' => self::p('يُقام مهرجان صحار في مركز صحار الترفيهي، وقد امتدت نسخته الرابعة من 22 ديسمبر 2025 إلى 31 يناير 2026 واستقبلت نحو 507 آلاف زائر.', 'وكان المركز مقر رالي عُمان الدولي في يناير 2026، وفيه خط النهاية. وموقعه على الخريطة تقريبي.'),
                    ],
                    'address' => $addr('Sohar', 'صحار'),
                    'map_pins' => $grounds,
                ],
                [
                    'slug' => 'sohar-camel-racetrack', 'category' => 'venues', 'lat' => 24.31407, 'lng' => 56.64073, 'wilayat' => $w['sohar'],
                    'name' => ['en' => 'Sohar Camel Racetrack', 'ar' => 'ميدان سباقات الهجن بصحار'],
                    'summary' => ['en' => 'Camel racing track of the Wilayat of Sohar, venue of the Sohar Camel Festival.', 'ar' => 'ميدان سباقات الهجن في ولاية صحار، وفيه يُقام مهرجان صحار للهجن.'],
                    'address' => $addr('Sohar', 'صحار'),
                    'map_pins' => ['Sohar Camel Racetrack'],
                ],
                [
                    'slug' => 'al-khaburah-equestrian-club', 'category' => 'venues', 'lat' => 23.94610, 'lng' => 57.12058, 'wilayat' => $w['khaburah'],
                    'name' => ['en' => 'Al Khaburah Equestrian Club', 'ar' => 'نادي الخابورة للفروسية'],
                    'summary' => ['en' => 'Equestrian club in the Al Breik heights, venue of the Al Khaburah Equestrian Festival.', 'ar' => 'نادي الفروسية في مرتفعات البريك، وفيه يُقام مهرجان الفروسية بالخابورة.'],
                    'description' => [
                        'en' => self::p('The club is in the Al Breik heights in the Wilayat of Al Khaburah. The map pin is approximate.'),
                        'ar' => self::p('يقع النادي في مرتفعات البريك بولاية الخابورة، وموقعه على الخريطة تقريبي.'),
                    ],
                    'address' => ['en' => 'Al Breik, Al Khaburah', 'ar' => 'البريك، الخابورة'],
                    'map_pins' => ['Al Khaburah Equestrian Club (Al Breik)'],
                ],
                [
                    'slug' => 'sohar-university', 'category' => 'venues', 'lat' => 24.29889, 'lng' => 56.78149, 'wilayat' => $w['sohar'],
                    'name' => ['en' => 'Sohar University', 'ar' => 'جامعة صحار'],
                    'summary' => ['en' => 'University in Sohar; its theatre hosts the Sohar University Theatre Festival.', 'ar' => 'جامعة في صحار، ويستضيف مسرحها مهرجان جامعة صحار المسرحي.'],
                    'address' => $addr('Sohar', 'صحار'),
                    'map_pins' => ['Sohar University'],
                ],
            ],
            'collections' => [
                [
                    'key' => 'services', 'layout' => 'list', 'icon' => 'support_agent', 'show_in_discover' => true,
                    'name' => ['en' => 'Visitor services', 'ar' => 'خدمات الزوار'],
                    'description' => ['en' => 'Services for visitors at the Sohar Festival grounds.', 'ar' => 'خدمات الزوار في أرض مهرجان صحار.'],
                    'items' => [
                        [
                            'slug' => 'ticket-office', 'icon' => 'confirmation_number',
                            'title' => ['en' => 'Ticket office', 'ar' => 'مكتب التذاكر'],
                            'subtitle' => ['en' => 'Sohar Festival grounds', 'ar' => 'أرض مهرجان صحار'],
                            'body' => ['en' => self::p('Buy your entry ticket in the app or at the ticket office, then show the ticket QR code at the gate.'),
                                'ar' => self::p('اشترِ تذكرة الدخول من التطبيق أو من مكتب التذاكر، ثم أظهر رمز QR للتذكرة عند البوابة.')],
                        ],
                        [
                            'slug' => 'information-desk', 'icon' => 'info', 'pin' => 'Festival Information Desk',
                            'title' => ['en' => 'Information desk', 'ar' => 'مكتب المعلومات'],
                            'subtitle' => ['en' => 'Visitor information and wallet help', 'ar' => 'استعلامات الزوار والمساعدة في استخدام المحفظة'],
                            'body' => ['en' => self::p('Ask the information desk about the programme, the festival grounds or using the wallet.'),
                                'ar' => self::p('اسأل مكتب المعلومات عن برنامج المهرجان وأرجائه وطريقة استخدام المحفظة.')],
                        ],
                        [
                            'slug' => 'first-aid', 'icon' => 'medical_services', 'pin' => 'Festival First Aid Point', 'phone' => '9999',
                            'title' => ['en' => 'First aid', 'ar' => 'الإسعافات الأولية'],
                            'subtitle' => ['en' => 'Festival first aid point', 'ar' => 'نقطة الإسعافات الأولية بالمهرجان'],
                            'body' => ['en' => self::p('First aid is available during festival hours. In an emergency call 9999.'),
                                'ar' => self::p('تُقدَّم الإسعافات الأولية طوال ساعات عمل المهرجان، وفي حالات الطوارئ اتصل بالرقم 9999.')],
                        ],
                        [
                            'slug' => 'lost-and-found', 'icon' => 'search', 'pin' => 'Festival Information Desk',
                            'title' => ['en' => 'Lost and found', 'ar' => 'المفقودات'],
                            'subtitle' => ['en' => 'At the information desk', 'ar' => 'في مكتب المعلومات'],
                            'body' => ['en' => self::p('Hand in found items or ask about lost ones at the festival information desk.'),
                                'ar' => self::p('سلِّم ما تجده أو اسأل عمّا فقدته في مكتب معلومات المهرجان.')],
                        ],
                        [
                            'slug' => 'prayer-rooms', 'icon' => 'mosque',
                            'title' => ['en' => 'Prayer rooms', 'ar' => 'المصليات'],
                            'subtitle' => ['en' => 'Ask at the information desk', 'ar' => 'اسأل في مكتب المعلومات'],
                            'body' => ['en' => self::p('Ask at the information desk for the nearest prayer room.'),
                                'ar' => self::p('اسأل في مكتب المعلومات عن أقرب مصلى.')],
                        ],
                        [
                            'slug' => 'parking', 'icon' => 'local_parking', 'pin' => 'Festival Parking',
                            'title' => ['en' => 'Car parking', 'ar' => 'مواقف السيارات'],
                            'subtitle' => ['en' => 'Main visitor car park', 'ar' => 'الموقف الرئيسي للزوار'],
                            'body' => ['en' => self::p('The main visitor car park of the festival grounds.'),
                                'ar' => self::p('الموقف الرئيسي لزوار أرض المهرجان.')],
                        ],
                    ],
                ],
                [
                    // Left EMPTY: the organizer adds the real partners and their logos.
                    'key' => 'partners', 'layout' => 'logos', 'icon' => 'handshake', 'show_in_discover' => true,
                    'name' => ['en' => 'Partners', 'ar' => 'الشركاء'],
                    'description' => ['en' => 'Partners and sponsors of the season.', 'ar' => 'شركاء الموسم ورعاته.'],
                    'items' => [],
                ],
                [
                    'key' => 'visitor-guide', 'layout' => 'cards', 'icon' => 'menu_book', 'show_in_discover' => true,
                    'name' => ['en' => 'Visitor guide', 'ar' => 'دليل الزائر'],
                    'description' => ['en' => 'Practical tips for your visit.', 'ar' => 'نصائح عملية لزيارتك.'],
                    'items' => [
                        [
                            'slug' => 'before-you-go', 'icon' => 'event_available',
                            'title' => ['en' => 'Before you go', 'ar' => 'قبل زيارتك'],
                            'subtitle' => ['en' => 'Check the dates in the app', 'ar' => 'راجع المواعيد في التطبيق'],
                            'body' => ['en' => self::p('Check event times in the app before you set out. Some season dates are estimates until the organizers announce them.'),
                                'ar' => self::p('راجع مواعيد الفعاليات في التطبيق قبل الخروج، فبعض مواعيد الموسم تقديرية حتى يعلنها المنظمون.')],
                        ],
                        [
                            'slug' => 'tickets-and-entry', 'icon' => 'qr_code_2',
                            'title' => ['en' => 'Tickets and entry', 'ar' => 'التذاكر والدخول'],
                            'subtitle' => ['en' => 'Your ticket is in the app', 'ar' => 'تذكرتك في التطبيق'],
                            'body' => ['en' => self::p('Keep your ticket in the app and show its QR code at the gate.'),
                                'ar' => self::p('احتفظ بتذكرتك في التطبيق، وأظهر رمز QR عند البوابة.')],
                        ],
                        [
                            'slug' => 'wallet-and-payment', 'icon' => 'account_balance_wallet',
                            'title' => ['en' => 'Wallet and payment', 'ar' => 'المحفظة والدفع'],
                            'subtitle' => ['en' => 'Top up once, pay at the vendors', 'ar' => 'اشحن مرة وادفع لدى التجار'],
                            'body' => ['en' => self::p('Top up your wallet in the app and pay with it at the vendors on the festival grounds.'),
                                'ar' => self::p('اشحن محفظتك في التطبيق وادفع بها لدى التجار في أرض المهرجان.')],
                        ],
                        [
                            'slug' => 'emergencies', 'icon' => 'emergency', 'phone' => '9999',
                            'title' => ['en' => 'Emergencies', 'ar' => 'الطوارئ'],
                            'subtitle' => ['en' => 'Call 9999', 'ar' => 'اتصل بالرقم 9999'],
                            'body' => ['en' => self::p('9999 is the national emergency number in Oman.'),
                                'ar' => self::p('رقم الطوارئ الوطني في سلطنة عُمان هو 9999.')],
                        ],
                    ],
                ],
            ],
            'pages' => [
                [
                    'slug' => 'about-north-al-batinah', 'icon' => 'location_city', 'show_in_more' => true,
                    'title' => ['en' => 'About North Al Batinah', 'ar' => 'عن محافظة شمال الباطنة'],
                    'body' => [
                        'en' => self::p(
                            'North Al Batinah Governorate has six wilayats: Sohar, Shinas, Liwa, Saham, Al Khaburah and Al Suwaiq.',
                            'The Sohar Festival is held at the Sohar Entertainment Centre in the Wilayat of Sohar, with a main committee chaired by the Governor of North Al Batinah. Its 4th edition, from 22 December 2025 to 31 January 2026, welcomed about 507,000 visitors.',
                            'During the season the governorate also hosts camel races in Sohar and Shinas, the Al Khaburah Equestrian Festival and the Oman International Rally, based in Sohar.',
                        ),
                        'ar' => self::p(
                            'تضم محافظة شمال الباطنة ست ولايات هي صحار وشناص ولوى وصحم والخابورة والسويق.',
                            'ويُقام مهرجان صحار في مركز صحار الترفيهي بولاية صحار، ويرأس لجنته الرئيسية محافظ شمال الباطنة. واستقبلت نسخته الرابعة، من 22 ديسمبر 2025 إلى 31 يناير 2026، نحو 507 آلاف زائر.',
                            'وتستضيف المحافظة في الموسم سباقات الهجن في صحار وشناص، ومهرجان الفروسية بالخابورة، ورالي عُمان الدولي الذي يتخذ من صحار مقرًا له.',
                        ),
                    ],
                ],
                [
                    'slug' => 'visiting-guidelines', 'icon' => 'checklist', 'show_in_more' => true,
                    'title' => ['en' => 'Visiting guidelines', 'ar' => 'إرشادات الزيارة'],
                    'body' => [
                        'en' => '<ul><li>Check event times in the app before you set out.</li><li>Keep your ticket in the app and show its QR code at the gate.</li><li>Opening hours and entry fees of some places are not listed yet; check them with the site.</li><li>Keep heritage sites and beaches clean.</li><li>In an emergency call 9999.</li></ul>',
                        'ar' => '<ul><li>راجع مواعيد الفعالية في التطبيق قبل الخروج.</li><li>احفظ تذكرتك في التطبيق، وأظهر رمز QR عند البوابة.</li><li>لم تُنشر بعد مواعيد بعض المواقع ورسوم دخولها، فتحقّق منها لدى الموقع نفسه.</li><li>حافظ على نظافة المواقع التراثية والشواطئ.</li><li>اتصل بالرقم 9999 في حالات الطوارئ.</li></ul>',
                    ],
                ],
            ],
        ];
    }

    public static function demo(): array
    {
        // Demo Arena (fictional), Muscat — tenant map centre 23.588, 58.3829.
        $lat = 23.5880;
        $lng = 58.3829;

        return [
            'categories' => [
                ['key' => 'venues', 'name' => ['en' => 'Venues', 'ar' => 'القاعات والساحات'], 'icon' => 'stadium', 'color' => '#4F46E5'],
                ['key' => 'food', 'name' => ['en' => 'Food & cafés', 'ar' => 'الطعام والمقاهي'], 'icon' => 'restaurant', 'color' => '#9333EA'],
                ['key' => 'outdoors', 'name' => ['en' => 'Outdoors', 'ar' => 'الهواء الطلق'], 'icon' => 'park', 'color' => '#059669'],
            ],
            'places' => [
                [
                    'slug' => 'demo-arena', 'category' => 'venues', 'lat' => $lat, 'lng' => $lng, 'featured' => true, 'all_events' => true,
                    'name' => ['en' => 'Demo Arena', 'ar' => 'ساحة العروض'],
                    'summary' => ['en' => 'The main venue of Demo Events: concerts, the expo and live shows.', 'ar' => 'المكان الرئيسي لفعاليات تجريبية، وفيه الحفلات والمعرض والعروض الحية.'],
                    'description' => ['en' => self::p('Exhibition Centre with Hall A for concerts and shows and an open expo floor.'), 'ar' => self::p('مركز معارض فيه القاعة أ للحفلات والعروض، وساحة مفتوحة للمعرض.')],
                    'opening_hours' => ['en' => 'Daily 10:00–23:00', 'ar' => 'يوميًا من 10:00 إلى 23:00'],
                    'address' => ['en' => 'Exhibition Centre, Muscat', 'ar' => 'مركز المعارض، مسقط'],
                ],
                [
                    'slug' => 'harbour-walk', 'category' => 'outdoors', 'lat' => $lat + 0.0042, 'lng' => $lng + 0.0051, 'featured' => true, 'fee' => 0,
                    'name' => ['en' => 'Harbour Walk', 'ar' => 'ممشى الميناء'],
                    'summary' => ['en' => 'A seafront promenade a few minutes from the arena.', 'ar' => 'ممشى على الواجهة البحرية على بعد دقائق من الساحة.'],
                    'opening_hours' => ['en' => 'Open all day', 'ar' => 'مفتوح طوال اليوم'],
                ],
                [
                    'slug' => 'palm-garden', 'category' => 'outdoors', 'lat' => $lat - 0.0031, 'lng' => $lng - 0.0036, 'fee' => 0,
                    'name' => ['en' => 'Palm Garden', 'ar' => 'حديقة النخيل'],
                    'summary' => ['en' => 'Shaded lawns and a playground for families.', 'ar' => 'مسطحات خضراء مظللة وملعب للأطفال.'],
                    'opening_hours' => ['en' => 'Daily 06:00–24:00', 'ar' => 'يوميًا من 06:00 إلى 24:00'],
                ],
                [
                    'slug' => 'lantern-food-street', 'category' => 'food', 'lat' => $lat + 0.0018, 'lng' => $lng - 0.0024,
                    'name' => ['en' => 'Lantern Food Street', 'ar' => 'شارع الفوانيس'],
                    'summary' => ['en' => 'Evening street food and dessert stalls.', 'ar' => 'أكشاك أكلات الشارع والحلويات في المساء.'],
                    'opening_hours' => ['en' => 'Daily 17:00–01:00', 'ar' => 'يوميًا من 17:00 إلى 01:00'],
                ],
            ],
            'collections' => [
                [
                    'key' => 'brands', 'layout' => 'logos', 'icon' => 'storefront', 'show_in_discover' => true,
                    'name' => ['en' => 'Brands', 'ar' => 'العلامات التجارية'],
                    'description' => ['en' => 'Brands at the Demo Events expo (fictional).', 'ar' => 'العلامات التجارية المشاركة في معرض فعاليات تجريبية، وأسماؤها خيالية.'],
                    'items' => [
                        ['slug' => 'dunefox-roasters', 'title' => ['en' => 'Dunefox Roasters', 'ar' => 'دون فوكس للقهوة'], 'subtitle' => ['en' => 'Specialty coffee', 'ar' => 'قهوة مختصة'], 'tags' => ['coffee'], 'featured' => true],
                        ['slug' => 'tidepool-toys', 'title' => ['en' => 'Tidepool Toys', 'ar' => 'تايدبول للألعاب'], 'subtitle' => ['en' => 'Toys and games', 'ar' => 'ألعاب الأطفال'], 'tags' => ['kids']],
                        ['slug' => 'saffron-lane-sweets', 'title' => ['en' => 'Saffron Lane Sweets', 'ar' => 'حلويات درب الزعفران'], 'subtitle' => ['en' => 'Omani sweets', 'ar' => 'حلويات عمانية'], 'tags' => ['food']],
                        ['slug' => 'orbitkite', 'title' => ['en' => 'Orbitkite', 'ar' => 'أوربت كايت'], 'subtitle' => ['en' => 'Kites and outdoor games', 'ar' => 'طائرات ورقية وألعاب خارجية'], 'tags' => ['kids', 'outdoors']],
                        ['slug' => 'falaj-and-co', 'title' => ['en' => 'Falaj & Co. Fragrances', 'ar' => 'فلج للعطور'], 'subtitle' => ['en' => 'Perfumes and oud', 'ar' => 'عطور وعود'], 'tags' => ['gifts']],
                    ],
                ],
                [
                    'key' => 'services', 'layout' => 'list', 'icon' => 'support_agent', 'show_in_discover' => true,
                    'name' => ['en' => 'Visitor services', 'ar' => 'خدمات الزوار'],
                    'description' => ['en' => 'Services at Demo Arena.', 'ar' => 'الخدمات في ساحة العروض.'],
                    'items' => [
                        ['slug' => 'info-desk', 'icon' => 'info', 'place' => 'demo-arena', 'title' => ['en' => 'Information desk', 'ar' => 'مكتب المعلومات'], 'subtitle' => ['en' => 'Main entrance', 'ar' => 'المدخل الرئيسي']],
                        ['slug' => 'cloakroom', 'icon' => 'checkroom', 'place' => 'demo-arena', 'title' => ['en' => 'Cloakroom', 'ar' => 'غرفة الأمانات'], 'subtitle' => ['en' => 'Next to Hall A', 'ar' => 'بجوار القاعة أ']],
                        ['slug' => 'first-aid', 'icon' => 'medical_services', 'place' => 'demo-arena', 'phone' => '9999', 'title' => ['en' => 'First aid', 'ar' => 'الإسعافات الأولية'], 'subtitle' => ['en' => 'Expo floor', 'ar' => 'ساحة المعرض']],
                        ['slug' => 'accessibility', 'icon' => 'accessible', 'title' => ['en' => 'Accessibility', 'ar' => 'تسهيلات ذوي الإعاقة'], 'subtitle' => ['en' => 'Wheelchairs on request', 'ar' => 'كراسٍ متحركة عند الطلب']],
                    ],
                ],
            ],
            'pages' => [
                [
                    'slug' => 'about', 'icon' => 'info', 'show_in_more' => true,
                    'title' => ['en' => 'About Demo Events', 'ar' => 'عن فعاليات تجريبية'],
                    'body' => [
                        'en' => self::p('Demo Events is a sample organizer that shows how the platform works: concerts, an expo and live shows at Demo Arena.', 'All names on this page and in Discover are fictional.'),
                        'ar' => self::p('فعاليات تجريبية منظِّم تجريبي يعرض طريقة عمل المنصة، ففيها حفلات ومعرض وعروض حية في ساحة العروض.', 'وجميع الأسماء في هذه الصفحة وفي قسم اكتشف خيالية.'),
                    ],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $tenant = app(TenantContext::class)->get();
        if (! $tenant) {
            return;
        }
        $defs = self::definitions($tenant->slug);

        $categories = [];
        foreach ($defs['categories'] as $i => $c) {
            $categories[$c['key']] = PlaceCategory::firstOrCreate(['key' => $c['key']], [
                'name' => $c['name'], 'icon' => $c['icon'], 'color' => $c['color'], 'sort' => $i + 1, 'is_active' => true,
            ]);
        }

        $places = [];
        foreach ($defs['places'] as $i => $p) {
            $place = Place::firstOrCreate(['slug' => $p['slug']], [
                'category_id' => $categories[$p['category']]->id ?? null,
                'name' => $p['name'],
                'summary' => $p['summary'] ?? null,
                'description' => isset($p['description']) ? array_map(fn ($h) => HtmlSanitizer::clean($h), $p['description']) : null,
                'latitude' => $p['lat'],
                'longitude' => $p['lng'],
                'address' => $p['address'] ?? null,
                'wilayat' => $p['wilayat'] ?? null,
                'opening_hours' => $p['opening_hours'] ?? null,
                'entry_fee_minor' => $p['fee'] ?? null,
                'featured' => (bool) ($p['featured'] ?? false),
                'sort' => $i + 1,
                'status' => 'published',
            ]);
            $places[$p['slug']] = $place;

            if (! empty($p['cover']) && ! $place->hasMedia('cover') && is_file($path = public_path($p['cover']))) {
                MediaPresenter::rememberSize($place->addMedia($path)->preservingOriginal()->toMediaCollection('cover'));
            }

            $eventIds = [];
            if (! empty($p['all_events'])) {
                $eventIds = Event::pluck('id')->all();
            } elseif (! empty($p['map_pins'])) {
                $pinIds = MapLocation::whereIn('name', $p['map_pins'])->pluck('id');
                $eventIds = Event::whereIn('map_location_id', $pinIds)->pluck('id')->all();
            }
            if ($eventIds) {
                $place->events()->syncWithoutDetaching($eventIds);
            }
        }

        foreach ($defs['collections'] as $i => $c) {
            $collection = ContentCollection::firstOrCreate(['key' => $c['key']], [
                'name' => $c['name'], 'description' => $c['description'] ?? null, 'icon' => $c['icon'] ?? null,
                'layout' => $c['layout'], 'show_in_discover' => $c['show_in_discover'] ?? true, 'sort' => $i + 1, 'is_active' => true,
            ]);
            foreach ($c['items'] as $j => $item) {
                $pin = ! empty($item['pin']) ? MapLocation::where('name', $item['pin'])->first() : null;
                $place = ! empty($item['place']) ? ($places[$item['place']] ?? Place::where('slug', $item['place'])->first()) : null;
                ContentItem::firstOrCreate(['collection_id' => $collection->id, 'slug' => $item['slug']], [
                    'title' => $item['title'],
                    'subtitle' => $item['subtitle'] ?? null,
                    'body' => isset($item['body']) ? array_map(fn ($h) => HtmlSanitizer::clean($h), $item['body']) : null,
                    'icon' => $item['icon'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'place_id' => $place?->id,
                    'latitude' => $pin ? (float) $pin->latitude : null,
                    'longitude' => $pin ? (float) $pin->longitude : null,
                    'tags' => $item['tags'] ?? null,
                    'featured' => (bool) ($item['featured'] ?? false),
                    'sort' => $j + 1,
                    'status' => 'published',
                ]);
            }
        }

        foreach ($defs['pages'] as $i => $page) {
            ContentPage::firstOrCreate(['slug' => $page['slug']], [
                'title' => $page['title'],
                'body' => array_map(fn ($h) => HtmlSanitizer::clean($h), $page['body']),
                'icon' => $page['icon'] ?? null,
                'show_in_more' => $page['show_in_more'] ?? true,
                'sort' => $i + 1,
                'status' => 'published',
            ]);
        }
    }
}
