<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MapLocation;

class MapLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            // Fort
            [
                'type' => 'fort',
                'name' => 'Sohar Fort',
                'name_ar' => 'قلعة صحار',
                'description' => 'Historic fort and main festival venue',
                'description_ar' => 'قلعة تاريخية ومكان المهرجان الرئيسي',
                'latitude' => 24.3570,
                'longitude' => 56.7520,
                'icon' => 'castle',
                'color' => hexdec('6D4C41'),
                'is_active' => true
            ],
            // Heritage
            [
                'type' => 'heritage',
                'name' => 'Sohar Heritage Village',
                'name_ar' => 'قرية صحار التراثية',
                'description' => 'Traditional heritage village showcasing Omani culture',
                'description_ar' => 'قرية تراثية تقليدية تعرض الثقافة العمانية',
                'latitude' => 24.3652,
                'longitude' => 56.7443,
                'icon' => 'museum',
                'color' => hexdec('8D6E63'),
                'is_active' => true
            ],
            // Beach
            [
                'type' => 'beach',
                'name' => 'Sohar Corniche',
                'name_ar' => 'كورنيش صحار',
                'description' => 'Beautiful waterfront promenade',
                'description_ar' => 'كورنيش جميل على الواجهة البحرية',
                'latitude' => 24.3623,
                'longitude' => 56.7502,
                'icon' => 'beach_access',
                'color' => hexdec('4FC3F7'),
                'is_active' => true
            ],
            // Parking
            [
                'type' => 'parking',
                'name' => 'Main Parking Area',
                'name_ar' => 'موقف السيارات الرئيسي',
                'description' => 'Large parking facility near the fort',
                'description_ar' => 'موقف سيارات كبير بالقرب من القلعة',
                'latitude' => 24.3555,
                'longitude' => 56.7535,
                'icon' => 'local_parking',
                'color' => hexdec('2196F3'),
                'is_active' => true
            ],
            // Information
            [
                'type' => 'info',
                'name' => 'Festival Information Center',
                'name_ar' => 'مركز معلومات المهرجان',
                'description' => 'Get information and assistance',
                'description_ar' => 'احصل على المعلومات والمساعدة',
                'latitude' => 24.3580,
                'longitude' => 56.7510,
                'icon' => 'info',
                'color' => hexdec('00BCD4'),
                'is_active' => true
            ],
            // Restaurant
            [
                'type' => 'restaurant',
                'name' => 'Festival Food Court',
                'name_ar' => 'منطقة الطعام',
                'description' => 'Various food vendors and restaurants',
                'description_ar' => 'مجموعة متنوعة من باعة الطعام والمطاعم',
                'latitude' => 24.3585,
                'longitude' => 56.7515,
                'icon' => 'restaurant',
                'color' => hexdec('FF5722'),
                'is_active' => true
            ],
            // Stage
            [
                'type' => 'stage',
                'name' => 'Main Performance Stage',
                'name_ar' => 'المسرح الرئيسي',
                'description' => 'Main stage for performances and shows',
                'description_ar' => 'المسرح الرئيسي للعروض والفعاليات',
                'latitude' => 24.3575,
                'longitude' => 56.7525,
                'icon' => 'theater_comedy',
                'color' => hexdec('9C27B0'),
                'is_active' => true
            ],
            // Entertainment
            [
                'type' => 'entertainment',
                'name' => 'Kids Play Area',
                'name_ar' => 'منطقة ألعاب الأطفال',
                'description' => 'Fun activities and games for children',
                'description_ar' => 'أنشطة وألعاب ممتعة للأطفال',
                'latitude' => 24.3560,
                'longitude' => 56.7530,
                'icon' => 'celebration',
                'color' => hexdec('FF9800'),
                'is_active' => true
            ],
            // Shopping
            [
                'type' => 'shopping',
                'name' => 'Souvenir Shop',
                'name_ar' => 'متجر الهدايا التذكارية',
                'description' => 'Traditional crafts and souvenirs',
                'description_ar' => 'الحرف التقليدية والهدايا التذكارية',
                'latitude' => 24.3568,
                'longitude' => 56.7518,
                'icon' => 'shopping_bag',
                'color' => hexdec('E91E63'),
                'is_active' => true
            ],
            // Restroom
            [
                'type' => 'restroom',
                'name' => 'Public Restrooms',
                'name_ar' => 'دورات المياه العامة',
                'description' => 'Clean public restroom facilities',
                'description_ar' => 'مرافق دورات مياه عامة نظيفة',
                'latitude' => 24.3563,
                'longitude' => 56.7522,
                'icon' => 'wc',
                'color' => hexdec('607D8B'),
                'is_active' => true
            ],
            // First Aid
            [
                'type' => 'first_aid',
                'name' => 'Medical Station',
                'name_ar' => 'محطة الإسعافات الأولية',
                'description' => 'First aid and medical assistance',
                'description_ar' => 'الإسعافات الأولية والمساعدة الطبية',
                'latitude' => 24.3578,
                'longitude' => 56.7512,
                'icon' => 'medical_services',
                'color' => hexdec('FF1744'),
                'is_active' => true
            ],
            // Emergency
            [
                'type' => 'emergency',
                'name' => 'Emergency Assembly Point',
                'name_ar' => 'نقطة التجمع للطوارئ',
                'description' => 'Emergency meeting point',
                'description_ar' => 'نقطة التجمع في حالات الطوارئ',
                'latitude' => 24.3590,
                'longitude' => 56.7505,
                'icon' => 'emergency',
                'color' => hexdec('F44336'),
                'is_active' => true
            ],
            // Venue
            [
                'type' => 'venue',
                'name' => 'Exhibition Hall',
                'name_ar' => 'قاعة المعارض',
                'description' => 'Indoor exhibition and display area',
                'description_ar' => 'منطقة المعارض والعرض الداخلية',
                'latitude' => 24.3572,
                'longitude' => 56.7528,
                'icon' => 'place',
                'color' => hexdec('4CAF50'),
                'is_active' => true
            ],
            // Facilities
            [
                'type' => 'facilities',
                'name' => 'Prayer Room',
                'name_ar' => 'مصلى',
                'description' => 'Prayer facilities for visitors',
                'description_ar' => 'مرافق الصلاة للزوار',
                'latitude' => 24.3567,
                'longitude' => 56.7520,
                'icon' => 'business',
                'color' => hexdec('795548'),
                'is_active' => true
            ],
        ];

        foreach ($locations as $locationData) {
            MapLocation::create($locationData);
        }
    }
}
