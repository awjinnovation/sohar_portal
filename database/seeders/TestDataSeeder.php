<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\Announcement;
use App\Models\MapLocation;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed map locations first
        $this->call(MapLocationSeeder::class);

        // Create Categories
        $categories = [
            [
                'name' => 'Music & Concerts',
                'name_ar' => 'الموسيقى والحفلات',
                'description' => 'Live music performances and concerts',
                'description_ar' => 'العروض الموسيقية الحية والحفلات',
                'icon_name' => 'music-note',
                'color_value' => 4280391411,
                'display_order' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Cultural Events',
                'name_ar' => 'الفعاليات الثقافية',
                'description' => 'Traditional and cultural activities',
                'description_ar' => 'الأنشطة التقليدية والثقافية',
                'icon_name' => 'museum',
                'color_value' => 4288423856,
                'display_order' => 2,
                'is_active' => true
            ],
            [
                'name' => 'Food & Dining',
                'name_ar' => 'الطعام وتناول الطعام',
                'description' => 'Food festivals and dining experiences',
                'description_ar' => 'مهرجانات الطعام وتجارب تناول الطعام',
                'icon_name' => 'restaurant',
                'color_value' => 4286141768,
                'display_order' => 3,
                'is_active' => true
            ]
        ];

        foreach ($categories as $categoryData) {
            Category::create($categoryData);
        }

        // Create Events
        $musicCategory = Category::where('name', 'Music & Concerts')->first();
        $culturalCategory = Category::where('name', 'Cultural Events')->first();
        $soharFort = MapLocation::where('name', 'Sohar Fort')->first();
        $heritageVillage = MapLocation::where('name', 'Sohar Heritage Village')->first();

        $events = [
            [
                'title' => 'Opening Ceremony Concert',
                'title_ar' => 'حفل الافتتاح',
                'description' => 'Grand opening ceremony with traditional and modern performances',
                'description_ar' => 'حفل افتتاح كبير مع عروض تقليدية وحديثة',
                'category_id' => $musicCategory->id,
                'start_time' => now()->addDays(5)->setTime(20, 0),
                'end_time' => now()->addDays(5)->setTime(23, 0),
                'location' => 'Main Stage, Sohar Fort',
                'location_ar' => 'المسرح الرئيسي، قلعة صحار',
                'map_location_id' => $soharFort?->id,
                'image_url' => 'https://picsum.photos/seed/concert-sohar/800/600',
                'images' => [
                    'https://picsum.photos/seed/concert1/800/600',
                    'https://picsum.photos/seed/concert2/800/600',
                    'https://picsum.photos/seed/concert3/800/600',
                ],
                'price' => 0,
                'currency' => 'OMR',
                'available_tickets' => 5000,
                'total_tickets' => 5000,
                'organizer_name' => 'Sohar Municipality',
                'organizer_name_ar' => 'بلدية صحار',
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'title' => 'Traditional Crafts Exhibition',
                'title_ar' => 'معرض الحرف التقليدية',
                'description' => 'Exhibition showcasing Omani traditional crafts and heritage',
                'description_ar' => 'معرض يعرض الحرف التقليدية العمانية والتراث',
                'category_id' => $culturalCategory->id,
                'start_time' => now()->addDays(7)->setTime(10, 0),
                'end_time' => now()->addDays(14)->setTime(22, 0),
                'location' => 'Heritage Village',
                'location_ar' => 'القرية التراثية',
                'map_location_id' => $heritageVillage?->id,
                'image_url' => 'https://picsum.photos/seed/crafts-heritage/800/600',
                'images' => [
                    'https://picsum.photos/seed/crafts1/800/600',
                    'https://picsum.photos/seed/crafts2/800/600',
                    'https://picsum.photos/seed/crafts3/800/600',
                    'https://picsum.photos/seed/crafts4/800/600',
                ],
                'price' => 2.000,
                'currency' => 'OMR',
                'available_tickets' => 1000,
                'total_tickets' => 1000,
                'is_featured' => true,
                'is_active' => true
            ]
        ];

        foreach ($events as $eventData) {
            Event::create($eventData);
        }

        // Create Restaurants
        $restaurants = [
            [
                'name' => 'Al Mina Restaurant',
                'name_ar' => 'مطعم الميناء',
                'description' => 'Authentic Omani cuisine with a modern twist',
                'description_ar' => 'المأكولات العمانية الأصيلة بلمسة عصرية',
                'cuisine' => 'Omani',
                'cuisine_ar' => 'عماني',
                'location' => 'Sohar Corniche',
                'location_ar' => 'كورنيش صحار',
                'rating' => 4.5,
                'total_ratings' => 150,
                'price_range' => '$$',
                'phone' => '+968 2684 5555',
                'website' => 'https://example.com',
                'is_open' => true,
                'is_featured' => true,
                'is_active' => true
            ],
            [
                'name' => 'Sea Breeze Cafe',
                'name_ar' => 'مقهى نسيم البحر',
                'description' => 'Casual dining with sea view',
                'description_ar' => 'تناول الطعام العادي مع إطلالة على البحر',
                'cuisine' => 'International',
                'cuisine_ar' => 'عالمي',
                'location' => 'Beach Road',
                'location_ar' => 'طريق الشاطئ',
                'rating' => 4.2,
                'total_ratings' => 89,
                'price_range' => '$',
                'is_open' => true,
                'is_active' => true
            ]
        ];

        foreach ($restaurants as $restaurantData) {
            Restaurant::create($restaurantData);
        }

        // Heritage Villages are seeded by HeritageVillageSeeder
        // No need to create them here to avoid duplicates

        // Create Announcements
        $announcements = [
            [
                'title' => 'Festival Opening Hours',
                'title_ar' => 'ساعات عمل المهرجان',
                'content' => 'The festival is open daily from 4:00 PM to 11:00 PM',
                'content_ar' => 'المهرجان مفتوح يوميًا من الساعة 4:00 مساءً حتى 11:00 مساءً',
                'type' => 'info',
                'priority' => 1,
                'is_pinned' => true,
                'is_active' => true,
                'start_datetime' => now(),
                'end_datetime' => now()->addDays(30),
                'created_by' => 1
            ],
            [
                'title' => 'Parking Information',
                'title_ar' => 'معلومات وقوف السيارات',
                'content' => 'Free parking available at designated areas',
                'content_ar' => 'مواقف مجانية متاحة في المناطق المخصصة',
                'type' => 'info',
                'is_active' => true,
                'start_datetime' => now(),
                'created_by' => 1
            ]
        ];

        foreach ($announcements as $announcementData) {
            Announcement::create($announcementData);
        }

        // Emergency contacts table was dropped by migration 2025_09_21_000000_simplify_database_structure
        // Data migrated to 'locations' table instead

        echo "Test data seeded successfully!\n";
    }
}