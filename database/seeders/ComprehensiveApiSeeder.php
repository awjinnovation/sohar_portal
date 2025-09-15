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
use App\Models\RestaurantFeature;
use App\Models\RestaurantImage;
use App\Models\RestaurantOpeningHour;
use Carbon\Carbon;

class ComprehensiveApiSeeder extends Seeder
{
    public function run(): void
    {
        // Update Events with future dates to ensure they appear in upcoming/today
        $this->updateEventsWithFutureDates();

        // Create Cultural Workshops
        $this->createCulturalWorkshops();

        // Create Traditional Activities
        $this->createTraditionalActivities();

        // Create Craft Demonstrations
        $this->createCraftDemonstrations();

        // Create Photo Spots
        $this->createPhotoSpots();

        // Create Village Attractions
        $this->createVillageAttractions();

        // Create Map Locations
        $this->createMapLocations();

        // Create Notifications
        $this->createNotifications();

        // Create Ticket Pricing
        $this->createTicketPricing();

        // Add Restaurant Features and Opening Hours
        $this->addRestaurantDetails();

        echo "Comprehensive API data seeded successfully!\n";
    }

    private function updateEventsWithFutureDates()
    {
        // Add more events and update existing ones
        $events = [
            [
                'title' => 'Traditional Music Night',
                'title_ar' => 'ليلة الموسيقى التقليدية',
                'description' => 'Experience authentic Omani traditional music performances',
                'description_ar' => 'استمتع بعروض الموسيقى التقليدية العمانية الأصيلة',
                'category_id' => 1,
                'start_time' => Carbon::today()->setTime(19, 0),
                'end_time' => Carbon::today()->setTime(22, 0),
                'location' => 'Cultural Stage',
                'location_ar' => 'المسرح الثقافي',
                'price' => 5.000,
                'currency' => 'OMR',
                'available_tickets' => 500,
                'total_tickets' => 500,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'title' => 'Children\'s Festival',
                'title_ar' => 'مهرجان الأطفال',
                'description' => 'Fun activities and entertainment for children',
                'description_ar' => 'أنشطة ممتعة وترفيه للأطفال',
                'category_id' => 2,
                'start_time' => Carbon::tomorrow()->setTime(16, 0),
                'end_time' => Carbon::tomorrow()->setTime(20, 0),
                'location' => 'Kids Zone',
                'location_ar' => 'منطقة الأطفال',
                'price' => 3.000,
                'currency' => 'OMR',
                'available_tickets' => 300,
                'total_tickets' => 300,
                'is_active' => true
            ],
            [
                'title' => 'Fireworks Show',
                'title_ar' => 'عرض الألعاب النارية',
                'description' => 'Spectacular fireworks display',
                'description_ar' => 'عرض مذهل للألعاب النارية',
                'category_id' => 1,
                'start_time' => Carbon::now()->addDays(2)->setTime(21, 0),
                'end_time' => Carbon::now()->addDays(2)->setTime(21, 30),
                'location' => 'Beach Front',
                'location_ar' => 'واجهة الشاطئ',
                'price' => 0,
                'currency' => 'OMR',
                'available_tickets' => 10000,
                'total_tickets' => 10000,
                'is_featured' => true,
                'is_active' => true
            ]
        ];

        foreach ($events as $event) {
            Event::create($event);
        }
    }

    private function createCulturalWorkshops()
    {
        $villages = HeritageVillage::first();
        $villageId = $villages ? $villages->id : 1;

        $workshops = [
            [
                'heritage_village_id' => $villageId,
                'title_en' => 'Traditional Pottery Making',
                'title_ar' => 'صناعة الفخار التقليدي',
                'description_en' => 'Learn the ancient art of Omani pottery making',
                'description_ar' => 'تعلم فن صناعة الفخار العماني القديم',
                'instructor_name' => 'Ahmed Al Rashdi',
                'image_url' => 'https://example.com/pottery-workshop.jpg',
                'duration_minutes' => 120,
                'max_participants' => 20,
                'price_omr' => 15.000,
                'skill_level' => 'beginner',
                'is_active' => true
            ],
            [
                'heritage_village_id' => $villageId,
                'title_en' => 'Arabic Calligraphy',
                'title_ar' => 'الخط العربي',
                'description_en' => 'Master the beautiful art of Arabic calligraphy',
                'description_ar' => 'أتقن فن الخط العربي الجميل',
                'instructor_name' => 'Fatima Al Zahra',
                'image_url' => 'https://example.com/calligraphy-workshop.jpg',
                'duration_minutes' => 90,
                'max_participants' => 15,
                'price_omr' => 10.000,
                'skill_level' => 'intermediate',
                'is_active' => true
            ],
            [
                'heritage_village_id' => $villageId,
                'title_en' => 'Traditional Weaving',
                'title_ar' => 'النسيج التقليدي',
                'description_en' => 'Learn traditional Omani weaving techniques',
                'description_ar' => 'تعلم تقنيات النسيج العماني التقليدي',
                'instructor_name' => 'Maryam Al Balushi',
                'image_url' => 'https://example.com/weaving-workshop.jpg',
                'duration_minutes' => 180,
                'max_participants' => 12,
                'price_omr' => 20.000,
                'skill_level' => 'advanced',
                'is_active' => true
            ]
        ];

        foreach ($workshops as $workshop) {
            CulturalWorkshop::create($workshop);
        }

        // Also add workshop schedules
        $workshops = CulturalWorkshop::all();
        foreach ($workshops as $workshop) {
            \DB::table('workshop_schedule')->insert([
                [
                    'workshop_id' => $workshop->id,
                    'schedule_time' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'workshop_id' => $workshop->id,
                    'schedule_time' => Carbon::now()->addDays(3)->setTime(14, 0)->toDateTimeString(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
    }

    private function createTraditionalActivities()
    {
        $activities = [
            [
                'name' => 'Al Razha Performance',
                'name_ar' => 'عرض الرزحة',
                'description' => 'Traditional Omani warrior dance performance',
                'description_ar' => 'عرض رقصة المحارب العماني التقليدية',
                'activity_time' => '20:00:00',
                'duration' => 30,
                'location' => 'Main Stage',
                'location_ar' => 'المسرح الرئيسي',
                'performers' => 'Al Wahiba Troupe',
                'performers_ar' => 'فرقة الوهيبة',
                'is_active' => true
            ],
            [
                'name' => 'Al Ayyala Dance',
                'name_ar' => 'رقصة العيالة',
                'description' => 'Traditional stick dance performance',
                'description_ar' => 'عرض رقصة العصا التقليدية',
                'activity_time' => '19:00:00',
                'duration' => 25,
                'location' => 'Cultural Stage',
                'location_ar' => 'المسرح الثقافي',
                'performers' => 'Sohar Cultural Group',
                'performers_ar' => 'مجموعة صحار الثقافية',
                'is_active' => true
            ],
            [
                'name' => 'Traditional Storytelling',
                'name_ar' => 'السرد القصصي التقليدي',
                'description' => 'Listen to traditional Omani folk tales',
                'description_ar' => 'استمع إلى الحكايات الشعبية العمانية التقليدية',
                'activity_time' => '17:00:00',
                'duration' => 45,
                'location' => 'Heritage Tent',
                'location_ar' => 'خيمة التراث',
                'performers' => 'Uncle Salem',
                'performers_ar' => 'العم سالم',
                'is_active' => true
            ]
        ];

        foreach ($activities as $activity) {
            TraditionalActivity::create($activity);
        }
    }

    private function createCraftDemonstrations()
    {
        $demonstrations = [
            [
                'craft_name' => 'Silver Making',
                'craft_name_ar' => 'صناعة الفضة',
                'craftsman_name' => 'Mohammed Al Balushi',
                'craftsman_name_ar' => 'محمد البلوشي',
                'description' => 'Watch traditional Omani silver jewelry being crafted',
                'description_ar' => 'شاهد صناعة المجوهرات الفضية العمانية التقليدية',
                'demonstration_time' => '11:00:00',
                'duration' => 45,
                'location' => 'Craft Village',
                'location_ar' => 'قرية الحرف',
                'is_active' => true
            ],
            [
                'craft_name' => 'Khanjar Making',
                'craft_name_ar' => 'صناعة الخنجر',
                'craftsman_name' => 'Ali Al Harthi',
                'craftsman_name_ar' => 'علي الحارثي',
                'description' => 'Traditional Omani dagger crafting demonstration',
                'description_ar' => 'عرض صناعة الخنجر العماني التقليدي',
                'demonstration_time' => '15:00:00',
                'duration' => 60,
                'location' => 'Heritage Workshop',
                'location_ar' => 'ورشة التراث',
                'is_active' => true
            ],
            [
                'craft_name' => 'Halwa Making',
                'craft_name_ar' => 'صناعة الحلوى',
                'craftsman_name' => 'Khalid Al Lawati',
                'craftsman_name_ar' => 'خالد اللواتي',
                'description' => 'Watch how traditional Omani halwa is made',
                'description_ar' => 'شاهد كيفية صنع الحلوى العمانية التقليدية',
                'demonstration_time' => '18:00:00',
                'duration' => 30,
                'location' => 'Food Heritage Area',
                'location_ar' => 'منطقة التراث الغذائي',
                'is_active' => true
            ]
        ];

        foreach ($demonstrations as $demo) {
            CraftDemonstration::create($demo);
        }
    }

    private function createPhotoSpots()
    {
        $spots = [
            [
                'name' => 'Traditional Omani Gate',
                'name_ar' => 'البوابة العمانية التقليدية',
                'description' => 'Beautiful traditional gate perfect for memorable photos',
                'description_ar' => 'بوابة تقليدية جميلة مثالية للصور التذكارية',
                'location' => 'Main Entrance',
                'location_ar' => 'المدخل الرئيسي',
                'latitude' => 24.4539,
                'longitude' => 56.6238,
                'best_time' => 'Golden hour (6-7 PM)',
                'best_time_ar' => 'الساعة الذهبية (6-7 مساءً)',
                'tips' => 'Best lighting during sunset',
                'tips_ar' => 'أفضل إضاءة عند غروب الشمس',
                'is_active' => true
            ],
            [
                'name' => 'Heritage Village Fountain',
                'name_ar' => 'نافورة القرية التراثية',
                'description' => 'Stunning fountain with traditional architecture backdrop',
                'description_ar' => 'نافورة مذهلة مع خلفية معمارية تقليدية',
                'location' => 'Heritage Village Center',
                'location_ar' => 'وسط القرية التراثية',
                'latitude' => 24.4541,
                'longitude' => 56.6240,
                'best_time' => 'Evening with lights',
                'best_time_ar' => 'المساء مع الأضواء',
                'tips' => 'Beautiful night shots with illumination',
                'tips_ar' => 'لقطات ليلية جميلة مع الإضاءة',
                'is_active' => true
            ],
            [
                'name' => 'Festival Landmark Sign',
                'name_ar' => 'لافتة المهرجان المميزة',
                'description' => 'Large illuminated festival sign',
                'description_ar' => 'لافتة المهرجان الكبيرة المضاءة',
                'location' => 'Festival Square',
                'location_ar' => 'ساحة المهرجان',
                'latitude' => 24.4543,
                'longitude' => 56.6242,
                'is_active' => true
            ]
        ];

        foreach ($spots as $spot) {
            PhotoSpot::create($spot);
        }
    }

    private function createVillageAttractions()
    {
        $villages = HeritageVillage::all();

        if ($villages->count() > 0) {
            $attractions = [
                [
                    'village_id' => $villages->first()->id,
                    'name' => 'Traditional House',
                    'name_ar' => 'البيت التقليدي',
                    'description' => 'Authentic Omani house with traditional architecture',
                    'description_ar' => 'بيت عماني أصيل بالعمارة التقليدية',
                    'opening_time' => '09:00:00',
                    'closing_time' => '22:00:00',
                    'is_active' => true
                ],
                [
                    'village_id' => $villages->first()->id,
                    'name' => 'Old Market',
                    'name_ar' => 'السوق القديم',
                    'description' => 'Traditional marketplace with local crafts and goods',
                    'description_ar' => 'سوق تقليدي مع الحرف والسلع المحلية',
                    'opening_time' => '10:00:00',
                    'closing_time' => '23:00:00',
                    'is_active' => true
                ],
                [
                    'village_id' => $villages->first()->id,
                    'name' => 'Falaj System Display',
                    'name_ar' => 'عرض نظام الفلج',
                    'description' => 'Traditional irrigation system demonstration',
                    'description_ar' => 'عرض نظام الري التقليدي',
                    'is_active' => true
                ]
            ];

            foreach ($attractions as $attraction) {
                VillageAttraction::create($attraction);
            }
        }
    }

    private function createMapLocations()
    {
        $locations = [
            [
                'name' => 'Main Stage',
                'name_ar' => 'المسرح الرئيسي',
                'category' => 'entertainment',
                'description' => 'Main performance stage for concerts and shows',
                'description_ar' => 'المسرح الرئيسي للحفلات والعروض',
                'latitude' => 24.4539,
                'longitude' => 56.6238,
                'icon' => 'stage',
                'is_active' => true
            ],
            [
                'name' => 'Food Court A',
                'name_ar' => 'ساحة الطعام أ',
                'category' => 'food',
                'description' => 'Various food stalls and restaurants',
                'description_ar' => 'أكشاك طعام ومطاعم متنوعة',
                'latitude' => 24.4540,
                'longitude' => 56.6239,
                'icon' => 'restaurant',
                'is_active' => true
            ],
            [
                'name' => 'Parking Area 1',
                'name_ar' => 'موقف السيارات 1',
                'category' => 'parking',
                'description' => 'Main parking area with 500 spaces',
                'description_ar' => 'موقف السيارات الرئيسي بسعة 500 سيارة',
                'latitude' => 24.4538,
                'longitude' => 56.6237,
                'icon' => 'parking',
                'is_active' => true
            ],
            [
                'name' => 'First Aid Station',
                'name_ar' => 'محطة الإسعافات الأولية',
                'category' => 'emergency',
                'description' => 'Medical assistance and first aid',
                'description_ar' => 'المساعدة الطبية والإسعافات الأولية',
                'latitude' => 24.4541,
                'longitude' => 56.6240,
                'icon' => 'medical',
                'is_active' => true
            ],
            [
                'name' => 'Main Entrance',
                'name_ar' => 'المدخل الرئيسي',
                'category' => 'entrance',
                'description' => 'Main festival entrance and ticket booth',
                'description_ar' => 'مدخل المهرجان الرئيسي وشباك التذاكر',
                'latitude' => 24.4537,
                'longitude' => 56.6236,
                'icon' => 'entrance',
                'is_active' => true
            ],
            [
                'name' => 'Restrooms Block A',
                'name_ar' => 'دورات المياه أ',
                'category' => 'facility',
                'description' => 'Public restroom facilities',
                'description_ar' => 'دورات مياه عامة',
                'latitude' => 24.4542,
                'longitude' => 56.6241,
                'icon' => 'restroom',
                'is_active' => true
            ]
        ];

        foreach ($locations as $location) {
            MapLocation::create($location);
        }
    }

    private function createNotifications()
    {
        $notifications = [
            [
                'title' => 'Welcome to Sohar Festival 2024',
                'title_ar' => 'مرحباً بكم في مهرجان صحار 2024',
                'message' => 'We are excited to have you at the festival!',
                'message_ar' => 'نحن متحمسون لوجودكم في المهرجان!',
                'type' => 'announcement',
                'priority' => 'high',
                'is_public' => true,
                'status' => 'active'
            ],
            [
                'title' => 'Special Discount Today',
                'title_ar' => 'خصم خاص اليوم',
                'message' => '20% off on all workshop registrations today only',
                'message_ar' => 'خصم 20% على جميع تسجيلات الورش اليوم فقط',
                'type' => 'promotion',
                'priority' => 'medium',
                'is_public' => true,
                'status' => 'active'
            ],
            [
                'title' => 'Fireworks Show Tonight',
                'title_ar' => 'عرض الألعاب النارية الليلة',
                'message' => 'Don\'t miss the spectacular fireworks at 9 PM',
                'message_ar' => 'لا تفوتوا عرض الألعاب النارية المذهل في الساعة 9 مساءً',
                'type' => 'event',
                'priority' => 'high',
                'is_public' => true,
                'status' => 'active'
            ]
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }

    private function createTicketPricing()
    {
        $pricing = [
            [
                'ticket_type' => 'general',
                'name' => 'General Admission',
                'name_ar' => 'الدخول العام',
                'description' => 'Access to all public areas and events',
                'description_ar' => 'الوصول إلى جميع المناطق والفعاليات العامة',
                'price' => 5.000,
                'currency' => 'OMR',
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonth(),
                'is_available' => true
            ],
            [
                'ticket_type' => 'vip',
                'name' => 'VIP Pass',
                'name_ar' => 'تذكرة VIP',
                'description' => 'VIP access with special seating and benefits',
                'description_ar' => 'دخول VIP مع مقاعد خاصة ومزايا',
                'price' => 25.000,
                'currency' => 'OMR',
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonth(),
                'is_available' => true
            ],
            [
                'ticket_type' => 'family',
                'name' => 'Family Package',
                'name_ar' => 'باقة العائلة',
                'description' => 'Special price for 2 adults and 2 children',
                'description_ar' => 'سعر خاص لشخصين بالغين وطفلين',
                'price' => 15.000,
                'currency' => 'OMR',
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonth(),
                'is_available' => true
            ],
            [
                'ticket_type' => 'student',
                'name' => 'Student Ticket',
                'name_ar' => 'تذكرة الطالب',
                'description' => 'Discounted ticket for students with valid ID',
                'description_ar' => 'تذكرة مخفضة للطلاب مع بطاقة صالحة',
                'price' => 3.000,
                'currency' => 'OMR',
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonth(),
                'is_available' => true
            ]
        ];

        foreach ($pricing as $price) {
            TicketPricing::create($price);
        }
    }

    private function addRestaurantDetails()
    {
        $restaurants = Restaurant::all();

        foreach ($restaurants as $restaurant) {
            // Add features
            $features = ['Outdoor Seating', 'WiFi', 'Parking', 'Family Friendly'];
            foreach ($features as $feature) {
                RestaurantFeature::create([
                    'restaurant_id' => $restaurant->id,
                    'name' => $feature,
                    'name_ar' => $feature // You can translate these
                ]);
            }

            // Add images
            RestaurantImage::create([
                'restaurant_id' => $restaurant->id,
                'image_url' => 'https://example.com/restaurant-' . $restaurant->id . '.jpg',
                'is_primary' => true
            ]);

            // Add opening hours
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($days as $day) {
                RestaurantOpeningHour::create([
                    'restaurant_id' => $restaurant->id,
                    'day' => $day,
                    'open_time' => '10:00',
                    'close_time' => '23:00',
                    'is_closed' => false
                ]);
            }
        }
    }
}