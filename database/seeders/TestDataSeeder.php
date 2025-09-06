<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\HeritageVillage;
use App\Models\Announcement;
use App\Models\EmergencyContact;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
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

        // Create Heritage Villages
        $villages = [
            [
                'name_en' => 'Maritime Heritage Village',
                'name_ar' => 'قرية التراث البحري',
                'description_en' => 'Experience the rich maritime history of Sohar',
                'description_ar' => 'استكشف التاريخ البحري الغني لصحار',
                'type' => 'maritime',
                'cover_image' => 'https://example.com/maritime.jpg',
                'opening_hours' => '9:00 AM - 10:00 PM',
                'is_active' => true
            ],
            [
                'name_en' => 'Agricultural Heritage Village',
                'name_ar' => 'قرية التراث الزراعي',
                'description_en' => 'Discover traditional farming methods and agricultural heritage',
                'description_ar' => 'اكتشف طرق الزراعة التقليدية والتراث الزراعي',
                'type' => 'agricultural',
                'cover_image' => 'https://example.com/agricultural.jpg',
                'opening_hours' => '9:00 AM - 10:00 PM',
                'is_active' => true
            ]
        ];

        foreach ($villages as $villageData) {
            HeritageVillage::create($villageData);
        }

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

        // Create Emergency Contacts
        $contacts = [
            [
                'service_name' => 'Police',
                'service_name_ar' => 'الشرطة',
                'phone_number' => '9999',
                'type' => 'police',
                'is_24_hours' => true,
                'display_order' => 1,
                'is_active' => true
            ],
            [
                'service_name' => 'Ambulance',
                'service_name_ar' => 'الإسعاف',
                'phone_number' => '9999',
                'type' => 'ambulance',
                'is_24_hours' => true,
                'display_order' => 2,
                'is_active' => true
            ],
            [
                'service_name' => 'Festival Security',
                'service_name_ar' => 'أمن المهرجان',
                'phone_number' => '+968 9555 5555',
                'type' => 'security',
                'location' => 'Main Gate',
                'location_ar' => 'البوابة الرئيسية',
                'is_24_hours' => false,
                'display_order' => 3,
                'is_active' => true
            ],
            [
                'service_name' => 'First Aid Station',
                'service_name_ar' => 'محطة الإسعافات الأولية',
                'phone_number' => '+968 9555 6666',
                'type' => 'first_aid',
                'location' => 'Near Food Court',
                'location_ar' => 'بالقرب من ساحة الطعام',
                'is_24_hours' => false,
                'display_order' => 4,
                'is_active' => true
            ]
        ];

        foreach ($contacts as $contactData) {
            EmergencyContact::create($contactData);
        }

        echo "Test data seeded successfully!\n";
    }
}