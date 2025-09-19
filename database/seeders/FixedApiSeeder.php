<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\CulturalWorkshop;
use App\Models\TraditionalActivity;
use App\Models\CraftDemonstration;
use App\Models\PhotoSpot;
use App\Models\VillageAttraction;
use App\Models\HeritageVillage;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\TicketPricing;
use App\Models\Restaurant;
use App\Models\Announcement;
use App\Models\Category;
use Carbon\Carbon;
use DB;

class FixedApiSeeder extends Seeder
{
    public function run(): void
    {
        // Create categories first
        $this->addCategories();

        // Create heritage village first
        $this->addHeritageVillage();

        // Add future events for today and upcoming
        $this->addFutureEvents();

        // Add cultural workshops with correct schema
        $this->addCulturalWorkshops();

        // Add traditional activities with correct schema
        $this->addTraditionalActivities();

        // Add other missing data
        $this->addPhotoSpots();
        $this->addMapLocations();
        $this->addNotifications();
        $this->addTicketPricing();
        $this->addRestaurants();
        $this->addAnnouncements();
        $this->addCraftDemonstrations();

        echo "Fixed API data seeded successfully!\n";
    }

    private function addCategories()
    {
        $categories = [
            ['id' => 1, 'name' => 'Cultural', 'name_ar' => 'ثقافي', 'description' => 'Cultural events and activities', 'description_ar' => 'الفعاليات والأنشطة الثقافية', 'icon_name' => 'culture', 'color_value' => hexdec('FF5722')],
            ['id' => 2, 'name' => 'Music', 'name_ar' => 'موسيقى', 'description' => 'Music concerts and performances', 'description_ar' => 'الحفلات الموسيقية والعروض', 'icon_name' => 'music', 'color_value' => hexdec('9C27B0')],
            ['id' => 3, 'name' => 'Food', 'name_ar' => 'طعام', 'description' => 'Food and culinary experiences', 'description_ar' => 'تجارب الطعام والطهي', 'icon_name' => 'food', 'color_value' => hexdec('4CAF50')],
            ['id' => 4, 'name' => 'Sports', 'name_ar' => 'رياضة', 'description' => 'Sports activities and competitions', 'description_ar' => 'الأنشطة الرياضية والمسابقات', 'icon_name' => 'sports', 'color_value' => hexdec('2196F3')],
            ['id' => 5, 'name' => 'Kids', 'name_ar' => 'أطفال', 'description' => 'Activities for children', 'description_ar' => 'أنشطة للأطفال', 'icon_name' => 'kids', 'color_value' => hexdec('FFC107')],
            ['id' => 6, 'name' => 'Arts', 'name_ar' => 'فنون', 'description' => 'Art exhibitions and workshops', 'description_ar' => 'المعارض الفنية وورش العمل', 'icon_name' => 'arts', 'color_value' => hexdec('E91E63')],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['id' => $category['id']], $category);
        }
    }

    private function addHeritageVillage()
    {
        HeritageVillage::updateOrCreate(
            ['id' => 1],
            [
                'name_en' => 'Sohar Heritage Village',
                'name_ar' => 'قرية صحار التراثية',
                'description_en' => 'Experience the rich cultural heritage of Sohar with traditional crafts, activities, and performances.',
                'description_ar' => 'استمتع بالتراث الثقافي الغني لصحار مع الحرف والأنشطة والعروض التقليدية.',
                'type' => 'maritime',
                'cover_image' => 'https://example.com/heritage-village.jpg',
                'opening_hours' => '10:00 AM - 10:00 PM',
                'virtual_tour_url' => 'https://example.com/virtual-tour',
                'is_active' => true
            ]
        );
    }

    private function addFutureEvents()
    {
        // Festival start and end dates
        $festivalStart = Carbon::create(2025, 9, 19, 10, 0, 0);
        $festivalEnd = Carbon::create(2025, 12, 11, 22, 0, 0);

        // Main Festival Event (spanning entire period)
        Event::create([
            'title' => 'Sohar Heritage Festival 2025',
            'title_ar' => 'مهرجان صحار التراثي 2025',
            'description' => 'Experience the rich cultural heritage of Sohar with daily activities, performances, and workshops. Book your time slot for each day. Each slot has a 1-hour duration with limited capacity.',
            'description_ar' => 'استمتع بالتراث الثقافي الغني لصحار مع الأنشطة اليومية والعروض وورش العمل. احجز موعدك لكل يوم. كل موعد مدته ساعة واحدة بسعة محدودة.',
            'category_id' => 1, // Cultural
            'start_time' => $festivalStart,
            'end_time' => $festivalEnd,
            'location' => 'Sohar Heritage Village',
            'location_ar' => 'قرية صحار التراثية',
            'price' => 5, // Per hour slot
            'currency' => 'OMR',
            'available_tickets' => 100, // Per hour slot
            'total_tickets' => 100, // Per hour slot capacity
            'is_featured' => true,
            'is_active' => true
        ]);

        // Traditional Music & Dance Festival
        Event::create([
            'title' => 'Traditional Music & Dance Festival',
            'title_ar' => 'مهرجان الموسيقى والرقص التقليدي',
            'description' => 'Daily performances of traditional Omani music and dance. Book your 1-hour viewing slot. Limited seats available per session.',
            'description_ar' => 'عروض يومية للموسيقى والرقص العماني التقليدي. احجز موعد المشاهدة لمدة ساعة. مقاعد محدودة لكل جلسة.',
            'category_id' => 2, // Music
            'start_time' => $festivalStart,
            'end_time' => Carbon::create(2025, 11, 30, 22, 0, 0),
            'location' => 'Main Amphitheater',
            'location_ar' => 'المدرج الرئيسي',
            'price' => 10,
            'currency' => 'OMR',
            'available_tickets' => 150, // Per hour slot
            'total_tickets' => 150,
            'is_featured' => true,
            'is_active' => true
        ]);

        // Food Festival
        Event::create([
            'title' => 'Omani Food Festival',
            'title_ar' => 'مهرجان الطعام العماني',
            'description' => 'Taste authentic Omani cuisine. Book your dining slot (1-hour sessions). Tables limited per hour.',
            'description_ar' => 'تذوق المأكولات العمانية الأصيلة. احجز موعد تناول الطعام (جلسات لمدة ساعة). الطاولات محدودة لكل ساعة.',
            'category_id' => 3, // Food
            'start_time' => Carbon::create(2025, 10, 1, 12, 0, 0),
            'end_time' => Carbon::create(2025, 12, 11, 22, 0, 0),
            'location' => 'Food Court',
            'location_ar' => 'ساحة الطعام',
            'price' => 15,
            'currency' => 'OMR',
            'available_tickets' => 80, // Per hour slot
            'total_tickets' => 80,
            'is_active' => true
        ]);

        // Kids Festival
        Event::create([
            'title' => 'Kids Entertainment Zone',
            'title_ar' => 'منطقة ترفيه الأطفال',
            'description' => 'Fun activities for children. Book 1-hour play sessions. Limited capacity for safety.',
            'description_ar' => 'أنشطة ممتعة للأطفال. احجز جلسات لعب لمدة ساعة. سعة محدودة للسلامة.',
            'category_id' => 5, // Kids
            'start_time' => $festivalStart,
            'end_time' => $festivalEnd,
            'location' => 'Kids Zone',
            'location_ar' => 'منطقة الأطفال',
            'price' => 3,
            'currency' => 'OMR',
            'available_tickets' => 50, // Per hour slot
            'total_tickets' => 50,
            'is_active' => true
        ]);

        // Art Exhibition
        Event::create([
            'title' => 'Contemporary & Traditional Art Exhibition',
            'title_ar' => 'معرض الفن المعاصر والتقليدي',
            'description' => 'Explore Omani art through the ages. Book your 1-hour gallery tour slot.',
            'description_ar' => 'استكشف الفن العماني عبر العصور. احجز موعد جولة المعرض لمدة ساعة.',
            'category_id' => 6, // Arts
            'start_time' => Carbon::create(2025, 9, 25, 10, 0, 0),
            'end_time' => Carbon::create(2025, 12, 5, 20, 0, 0),
            'location' => 'Art Gallery Hall',
            'location_ar' => 'قاعة المعرض الفني',
            'price' => 8,
            'currency' => 'OMR',
            'available_tickets' => 60, // Per hour slot
            'total_tickets' => 60,
            'is_active' => true
        ]);

        // Sports Activities
        Event::create([
            'title' => 'Traditional Sports & Games',
            'title_ar' => 'الرياضات والألعاب التقليدية',
            'description' => 'Participate in traditional Omani sports. Book your 1-hour activity slot.',
            'description_ar' => 'شارك في الرياضات العمانية التقليدية. احجز موعد نشاطك لمدة ساعة.',
            'category_id' => 4, // Sports
            'start_time' => Carbon::create(2025, 10, 15, 16, 0, 0),
            'end_time' => Carbon::create(2025, 11, 30, 21, 0, 0),
            'location' => 'Sports Arena',
            'location_ar' => 'الساحة الرياضية',
            'price' => 7,
            'currency' => 'OMR',
            'available_tickets' => 40, // Per hour slot
            'total_tickets' => 40,
            'is_featured' => true,
            'is_active' => true
        ]);
    }

    private function addCulturalWorkshops()
    {
        $village = HeritageVillage::first();
        if (!$village) return;

        // Create workshops
        $workshops = [
            [
                'heritage_village_id' => $village->id,
                'title_en' => 'Pottery Workshop',
                'title_ar' => 'ورشة الفخار',
                'description_en' => 'Learn pottery making',
                'description_ar' => 'تعلم صناعة الفخار',
                'instructor_name' => 'Ahmed Ali',
                'image_url' => 'https://example.com/pottery.jpg',
                'duration_minutes' => 120,
                'max_participants' => 20,
                'price_omr' => 15.00,
                'skill_level' => 'beginner',
                'is_active' => true
            ],
            [
                'heritage_village_id' => $village->id,
                'title_en' => 'Calligraphy Workshop',
                'title_ar' => 'ورشة الخط',
                'description_en' => 'Arabic calligraphy basics',
                'description_ar' => 'أساسيات الخط العربي',
                'instructor_name' => 'Fatima Hassan',
                'image_url' => 'https://example.com/calligraphy.jpg',
                'duration_minutes' => 90,
                'max_participants' => 15,
                'price_omr' => 10.00,
                'skill_level' => 'intermediate',
                'is_active' => true
            ]
        ];

        foreach ($workshops as $workshop) {
            $created = CulturalWorkshop::create($workshop);

            // Add schedules
            DB::table('workshop_schedule')->insert([
                [
                    'workshop_id' => $created->id,
                    'schedule_time' => Carbon::tomorrow()->setTime(10, 0),
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'workshop_id' => $created->id,
                    'schedule_time' => Carbon::now()->addDays(2)->setTime(14, 0),
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
    }

    private function addTraditionalActivities()
    {
        $village = HeritageVillage::first();
        if (!$village) return;

        $activities = [
            [
                'heritage_village_id' => $village->id,
                'activity_name_en' => 'Traditional Dance',
                'activity_name_ar' => 'الرقص التقليدي',
                'description_en' => 'Watch traditional Omani dances',
                'description_ar' => 'شاهد الرقصات العمانية التقليدية',
                'image_url' => 'https://example.com/dance.jpg',
                'is_interactive' => true,
                'age_recommendation' => 'All ages',
                'timing' => '7:00 PM - 8:00 PM',
                'is_active' => true
            ],
            [
                'heritage_village_id' => $village->id,
                'activity_name_en' => 'Storytelling',
                'activity_name_ar' => 'رواية القصص',
                'description_en' => 'Traditional Omani stories',
                'description_ar' => 'قصص عمانية تقليدية',
                'image_url' => 'https://example.com/story.jpg',
                'is_interactive' => false,
                'age_recommendation' => 'Children',
                'timing' => '5:00 PM - 6:00 PM',
                'is_active' => true
            ]
        ];

        foreach ($activities as $activity) {
            TraditionalActivity::create($activity);
        }
    }

    private function addPhotoSpots()
    {
        $village = HeritageVillage::first();
        if (!$village) return;

        $spots = [
            [
                'heritage_village_id' => $village->id,
                'name_en' => 'Main Gate Photo Spot',
                'name_ar' => 'نقطة تصوير البوابة الرئيسية',
                'description_en' => 'Perfect spot for entrance photos',
                'description_ar' => 'مكان مثالي لصور المدخل',
                'image_url' => 'https://example.com/gate-photo.jpg',
                'best_time_for_photos' => 'Sunset - 6:00 PM to 7:00 PM',
                'is_active' => true
            ],
            [
                'heritage_village_id' => $village->id,
                'name_en' => 'Heritage Fountain',
                'name_ar' => 'نافورة التراث',
                'description_en' => 'Beautiful fountain area',
                'description_ar' => 'منطقة نافورة جميلة',
                'image_url' => 'https://example.com/fountain-photo.jpg',
                'best_time_for_photos' => 'Evening with lights',
                'is_active' => true
            ]
        ];

        foreach ($spots as $spot) {
            PhotoSpot::create($spot);
        }
    }

    private function addMapLocations()
    {
        $locations = [
            [
                'type' => 'entertainment',
                'name' => 'Main Stage',
                'name_ar' => 'المسرح الرئيسي',
                'description' => 'Main performance area',
                'description_ar' => 'منطقة العروض الرئيسية',
                'latitude' => 24.4539,
                'longitude' => 56.6238,
                'icon' => 'stage',
                'color' => '#FF5722',
                'is_active' => true
            ],
            [
                'type' => 'food',
                'name' => 'Food Court',
                'name_ar' => 'ساحة الطعام',
                'description' => 'Various food options',
                'description_ar' => 'خيارات طعام متنوعة',
                'latitude' => 24.4540,
                'longitude' => 56.6239,
                'icon' => 'restaurant',
                'color' => '#4CAF50',
                'is_active' => true
            ],
            [
                'type' => 'parking',
                'name' => 'Parking Area',
                'name_ar' => 'موقف السيارات',
                'description' => 'Free parking',
                'description_ar' => 'موقف مجاني',
                'latitude' => 24.4538,
                'longitude' => 56.6237,
                'icon' => 'parking',
                'color' => '#2196F3',
                'is_active' => true
            ]
        ];

        foreach ($locations as $location) {
            // Convert hex color to integer
            if (isset($location['color'])) {
                $location['color'] = hexdec(str_replace('#', '', $location['color']));
            }
            MapLocation::create($location);
        }
    }

    private function addNotifications()
    {
        $notifications = [
            [
                'title' => 'Welcome to Festival',
                'title_ar' => 'مرحباً بكم في المهرجان',
                'body' => 'Enjoy your visit!',
                'body_ar' => 'استمتع بزيارتك!',
                'type' => 'announcement',
                'is_read' => false
            ],
            [
                'title' => 'Special Offers Today',
                'title_ar' => 'عروض خاصة اليوم',
                'body' => '20% discount on workshops',
                'body_ar' => 'خصم 20% على الورش',
                'type' => 'promotion',
                'is_read' => false
            ]
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }

    private function addTicketPricing()
    {
        $events = Event::take(3)->get();

        foreach ($events as $event) {
            $pricing = [
                [
                    'event_id' => $event->id,
                    'ticket_type' => 'standard',
                    'price' => 5.000,
                    'available_quantity' => 100,
                    'benefits' => 'General admission',
                    'benefits_ar' => 'دخول عام'
                ],
                [
                    'event_id' => $event->id,
                    'ticket_type' => 'vip',
                    'price' => 25.000,
                    'available_quantity' => 20,
                    'benefits' => 'VIP seating, complimentary refreshments',
                    'benefits_ar' => 'جلوس VIP، مرطبات مجانية'
                ]
            ];

            foreach ($pricing as $price) {
                TicketPricing::create($price);
            }
        }
    }

    private function addRestaurants()
    {
        $restaurants = [
            [
                'name' => 'Traditional Omani Kitchen',
                'name_ar' => 'المطبخ العماني التقليدي',
                'description' => 'Authentic Omani cuisine',
                'description_ar' => 'المأكولات العمانية الأصيلة',
                'cuisine' => 'Omani',
                'cuisine_ar' => 'عماني',
                'location' => 'Food Court Area A',
                'location_ar' => 'منطقة الطعام أ',
                'latitude' => 24.4541,
                'longitude' => 56.6240,
                'phone' => '+968 12345678',
                'website' => 'https://soharfestival.om/restaurants/omani',
                'price_range' => '$$',
                'rating' => 4.5,
                'total_ratings' => 120,
                'is_open' => true,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'name' => 'International Flavors',
                'name_ar' => 'النكهات العالمية',
                'description' => 'Various international cuisines',
                'description_ar' => 'مأكولات عالمية متنوعة',
                'cuisine' => 'International',
                'cuisine_ar' => 'عالمي',
                'location' => 'Food Court Area B',
                'location_ar' => 'منطقة الطعام ب',
                'latitude' => 24.4542,
                'longitude' => 56.6241,
                'phone' => '+968 12345679',
                'website' => 'https://soharfestival.om/restaurants/intl',
                'price_range' => '$$$',
                'rating' => 4.2,
                'total_ratings' => 85,
                'is_open' => true,
                'is_featured' => false,
                'is_active' => true
            ]
        ];

        foreach ($restaurants as $restaurant) {
            Restaurant::create($restaurant);
        }
    }

    private function addAnnouncements()
    {
        $user = \App\Models\User::first();
        if (!$user) return;

        $announcements = [
            [
                'title' => 'Festival Schedule Update',
                'title_ar' => 'تحديث جدول المهرجان',
                'content' => 'New events added for the weekend',
                'content_ar' => 'فعاليات جديدة أضيفت لنهاية الأسبوع',
                'type' => 'info',
                'priority' => 2,
                'is_pinned' => true,
                'is_active' => true,
                'start_datetime' => now(),
                'end_datetime' => now()->addDays(7),
                'created_by' => $user->id
            ],
            [
                'title' => 'Parking Information',
                'title_ar' => 'معلومات مواقف السيارات',
                'content' => 'Free parking available at Gate 2',
                'content_ar' => 'مواقف مجانية متاحة عند البوابة 2',
                'type' => 'info',
                'priority' => 1,
                'is_pinned' => false,
                'is_active' => true,
                'start_datetime' => now(),
                'end_datetime' => now()->addDays(30),
                'created_by' => $user->id
            ]
        ];

        foreach ($announcements as $announcement) {
            Announcement::create($announcement);
        }
    }

    private function addCraftDemonstrations()
    {
        $village = HeritageVillage::first();
        if (!$village) return;

        $demonstrations = [
            [
                'heritage_village_id' => $village->id,
                'craft_name_en' => 'Silver Making',
                'craft_name_ar' => 'صناعة الفضة',
                'artisan_name' => 'Mohammed Al-Salmi',
                'description_en' => 'Traditional silver jewelry making',
                'description_ar' => 'صناعة المجوهرات الفضية التقليدية',
                'demonstration_times' => '10:00 AM - 12:00 PM',
                'duration_minutes' => 120,
                'can_try_hands_on' => true,
                'is_active' => true
            ],
            [
                'heritage_village_id' => $village->id,
                'craft_name_en' => 'Pottery',
                'craft_name_ar' => 'الفخار',
                'artisan_name' => 'Ali Al-Rashdi',
                'description_en' => 'Traditional pottery making',
                'description_ar' => 'صناعة الفخار التقليدية',
                'demonstration_times' => '2:00 PM - 4:00 PM',
                'duration_minutes' => 120,
                'can_try_hands_on' => false,
                'is_active' => true
            ]
        ];

        foreach ($demonstrations as $demo) {
            CraftDemonstration::create($demo);
        }
    }
}