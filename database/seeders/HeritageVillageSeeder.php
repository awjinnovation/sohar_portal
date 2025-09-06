<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HeritageVillage;
use App\Models\VillageImage;
use App\Models\VillageAttraction;
use App\Models\CraftDemonstration;
use App\Models\TraditionalActivity;
use App\Models\CulturalWorkshop;
use App\Models\PhotoSpot;

class HeritageVillageSeeder extends Seeder
{
    public function run(): void
    {
        // Maritime Heritage Village
        $maritimeVillage = HeritageVillage::create([
            'name_en' => 'Maritime Heritage Village',
            'name_ar' => 'قرية التراث البحري',
            'description_en' => "Explore Oman's rich maritime history through traditional dhow building, pearl diving demonstrations, and coastal life exhibits.",
            'description_ar' => 'استكشف تاريخ عُمان البحري الغني من خلال بناء السفن التقليدية وعروض الغوص على اللؤلؤ ومعارض الحياة الساحلية.',
            'type' => 'maritime',
            'cover_image' => 'https://example.com/maritime-village.jpg',
            'opening_hours' => '9:00 AM - 10:00 PM',
            'is_active' => true
        ]);

        // Add images for Maritime Village
        VillageImage::create([
            'heritage_village_id' => $maritimeVillage->id,
            'image_url' => 'https://example.com/maritime-1.jpg',
            'caption_en' => 'Traditional Dhow Building',
            'caption_ar' => 'بناء السفن التقليدية',
            'display_order' => 1,
            'is_featured' => true
        ]);

        // Add attractions for Maritime Village
        $attractions = [
            ['en' => 'Traditional Dhow Building Workshop', 'ar' => 'ورشة بناء السفن التقليدية'],
            ['en' => 'Pearl Diving Experience', 'ar' => 'تجربة الغوص على اللؤلؤ'],
            ['en' => 'Fishing Net Weaving Demonstrations', 'ar' => 'عروض نسج شباك الصيد'],
            ['en' => 'Maritime Museum Exhibition', 'ar' => 'معرض المتحف البحري'],
            ['en' => "Sailor's Life Recreation", 'ar' => 'إعادة تمثيل حياة البحارة'],
        ];

        foreach ($attractions as $index => $attraction) {
            VillageAttraction::create([
                'heritage_village_id' => $maritimeVillage->id,
                'name_en' => $attraction['en'],
                'name_ar' => $attraction['ar'],
                'description_en' => 'Experience the authentic ' . strtolower($attraction['en']) . ' of traditional Omani maritime culture.',
                'description_ar' => 'عش تجربة ' . $attraction['ar'] . ' الأصيلة في الثقافة البحرية العمانية التقليدية.',
                'visiting_hours' => '9:00 AM - 9:00 PM',
                'is_active' => true
            ]);
        }

        // Add craft demonstrations for Maritime Village
        CraftDemonstration::create([
            'heritage_village_id' => $maritimeVillage->id,
            'craft_name_en' => 'Traditional Dhow Building',
            'craft_name_ar' => 'بناء السفن التقليدية',
            'description_en' => 'Watch master craftsmen build traditional Omani dhows using time-honored techniques.',
            'description_ar' => 'شاهد الحرفيين المهرة وهم يبنون السفن العمانية التقليدية باستخدام تقنيات عريقة.',
            'artisan_name' => 'Master Abdullah Al-Sinawi',
            'demonstration_times' => '10:00 AM, 2:00 PM, 6:00 PM',
            'materials_used_en' => 'Wood, rope, traditional tools',
            'materials_used_ar' => 'الخشب، الحبال، الأدوات التقليدية',
            'historical_significance_en' => 'Dhow building has been central to Omani maritime trade for centuries.',
            'historical_significance_ar' => 'كان بناء السفن محورياً في التجارة البحرية العمانية لقرون.',
            'duration_minutes' => 45,
            'can_try_hands_on' => true,
            'is_active' => true
        ]);

        CraftDemonstration::create([
            'heritage_village_id' => $maritimeVillage->id,
            'craft_name_en' => 'Fishing Net Weaving',
            'craft_name_ar' => 'نسج شباك الصيد',
            'description_en' => 'Learn the ancient art of weaving fishing nets by hand.',
            'description_ar' => 'تعلم الفن القديم لنسج شباك الصيد يدوياً.',
            'artisan_name' => 'Salim Al-Harthy',
            'demonstration_times' => '11:00 AM, 3:00 PM, 7:00 PM',
            'duration_minutes' => 30,
            'can_try_hands_on' => true,
            'is_active' => true
        ]);

        // Add traditional activities for Maritime Village
        TraditionalActivity::create([
            'heritage_village_id' => $maritimeVillage->id,
            'activity_name_en' => 'Pearl Diving Experience',
            'activity_name_ar' => 'تجربة الغوص على اللؤلؤ',
            'description_en' => 'Experience the traditional pearl diving techniques in a safe environment.',
            'description_ar' => 'جرب تقنيات الغوص التقليدية على اللؤلؤ في بيئة آمنة.',
            'image_url' => '/images/activities/pearl-diving.jpg',
            'is_interactive' => true,
            'age_recommendation' => '12+',
            'timing' => 'Every 2 hours',
            'is_active' => true
        ]);

        TraditionalActivity::create([
            'heritage_village_id' => $maritimeVillage->id,
            'activity_name_en' => 'Traditional Boat Rowing',
            'activity_name_ar' => 'التجديف التقليدي',
            'description_en' => 'Try your hand at rowing traditional Omani boats.',
            'description_ar' => 'جرب التجديف في القوارب العمانية التقليدية.',
            'image_url' => '/images/activities/boat-rowing.jpg',
            'is_interactive' => true,
            'age_recommendation' => 'All ages',
            'timing' => 'Continuous',
            'is_active' => true
        ]);

        // Add cultural workshops for Maritime Village
        CulturalWorkshop::create([
            'heritage_village_id' => $maritimeVillage->id,
            'title_en' => 'Ancient Navigation Techniques',
            'title_ar' => 'تقنيات الملاحة القديمة',
            'description_en' => 'Learn how Omani sailors navigated using stars and traditional instruments.',
            'description_ar' => 'تعلم كيف كان البحارة العمانيون يبحرون باستخدام النجوم والأدوات التقليدية.',
            'instructor_name' => 'Captain Mohammed Al-Balushi',
            'image_url' => '/images/workshops/navigation.jpg',
            'duration_minutes' => 90,
            'max_participants' => 15,
            'price_omr' => 5.000,
            'skill_level' => 'beginner',
            'is_active' => true
        ]);

        // Add photo spots for Maritime Village
        PhotoSpot::create([
            'heritage_village_id' => $maritimeVillage->id,
            'name_en' => 'Dhow at Sunset',
            'name_ar' => 'السفينة عند الغروب',
            'description_en' => 'Capture stunning photos with traditional dhows against the sunset.',
            'description_ar' => 'التقط صوراً مذهلة للسفن التقليدية مع غروب الشمس.',
            'image_url' => '/images/spots/dhow-sunset.jpg',
            'best_time_for_photos' => 'Golden hour (5:30 PM - 6:30 PM)',
            'is_active' => true
        ]);

        // Agricultural Heritage Village
        $agriculturalVillage = HeritageVillage::create([
            'name_en' => 'Agricultural Heritage Village',
            'name_ar' => 'قرية التراث الزراعي',
            'description_en' => "Discover Oman's agricultural traditions, falaj irrigation systems, and date palm cultivation.",
            'description_ar' => 'اكتشف التقاليد الزراعية العمانية ونظم الري بالأفلاج وزراعة النخيل.',
            'type' => 'agricultural',
            'cover_image' => 'https://example.com/agricultural-village.jpg',
            'opening_hours' => '9:00 AM - 10:00 PM',
            'is_active' => true
        ]);

        // Add attractions for Agricultural Village
        $agriAttractions = [
            ['en' => 'Traditional Falaj Irrigation System', 'ar' => 'نظام الري بالأفلاج التقليدي'],
            ['en' => 'Date Palm Plantation', 'ar' => 'مزرعة النخيل'],
            ['en' => 'Traditional Farming Tools Exhibition', 'ar' => 'معرض أدوات الزراعة التقليدية'],
            ['en' => 'Organic Garden', 'ar' => 'الحديقة العضوية'],
            ['en' => 'Agricultural Products Market', 'ar' => 'سوق المنتجات الزراعية'],
        ];

        foreach ($agriAttractions as $attraction) {
            VillageAttraction::create([
                'heritage_village_id' => $agriculturalVillage->id,
                'name_en' => $attraction['en'],
                'name_ar' => $attraction['ar'],
                'description_en' => 'Explore the ' . strtolower($attraction['en']) . ' showcasing traditional Omani agricultural heritage.',
                'description_ar' => 'استكشف ' . $attraction['ar'] . ' الذي يعرض التراث الزراعي العماني التقليدي.',
                'visiting_hours' => '9:00 AM - 9:00 PM',
                'is_active' => true
            ]);
        }

        // Add craft demonstrations for Agricultural Village
        CraftDemonstration::create([
            'heritage_village_id' => $agriculturalVillage->id,
            'craft_name_en' => 'Date Processing & Preservation',
            'craft_name_ar' => 'معالجة وحفظ التمور',
            'description_en' => 'Learn traditional methods of processing and preserving dates.',
            'description_ar' => 'تعلم الطرق التقليدية لمعالجة وحفظ التمور.',
            'artisan_name' => 'Fatima Al-Wahaibi',
            'demonstration_times' => '10:30 AM, 3:30 PM, 6:30 PM',
            'duration_minutes' => 40,
            'can_try_hands_on' => true,
            'is_active' => true
        ]);

        CraftDemonstration::create([
            'heritage_village_id' => $agriculturalVillage->id,
            'craft_name_en' => 'Palm Frond Basket Weaving',
            'craft_name_ar' => 'نسج السلال من سعف النخيل',
            'description_en' => 'Watch artisans create beautiful baskets from palm fronds.',
            'description_ar' => 'شاهد الحرفيين وهم يصنعون سلالاً جميلة من سعف النخيل.',
            'artisan_name' => 'Maryam Al-Busaidi',
            'demonstration_times' => '11:30 AM, 4:30 PM, 7:30 PM',
            'duration_minutes' => 35,
            'can_try_hands_on' => true,
            'is_active' => true
        ]);

        // Add traditional activities for Agricultural Village
        TraditionalActivity::create([
            'heritage_village_id' => $agriculturalVillage->id,
            'activity_name_en' => 'Falaj System Tour',
            'activity_name_ar' => 'جولة في نظام الأفلاج',
            'description_en' => 'Guided tour of the traditional irrigation system.',
            'description_ar' => 'جولة مع مرشد في نظام الري التقليدي.',
            'image_url' => '/images/activities/falaj-tour.jpg',
            'is_interactive' => false,
            'age_recommendation' => 'All ages',
            'timing' => 'Every hour',
            'is_active' => true
        ]);

        TraditionalActivity::create([
            'heritage_village_id' => $agriculturalVillage->id,
            'activity_name_en' => 'Traditional Date Varieties Tasting',
            'activity_name_ar' => 'تذوق أصناف التمور التقليدية',
            'description_en' => 'Sample different varieties of Omani dates.',
            'description_ar' => 'تذوق أصناف مختلفة من التمور العمانية.',
            'image_url' => '/images/activities/date-tasting.jpg',
            'is_interactive' => true,
            'age_recommendation' => 'All ages',
            'timing' => 'Continuous',
            'is_active' => true
        ]);

        // Add cultural workshops for Agricultural Village
        CulturalWorkshop::create([
            'heritage_village_id' => $agriculturalVillage->id,
            'title_en' => 'Traditional Organic Farming',
            'title_ar' => 'الزراعة العضوية التقليدية',
            'description_en' => 'Learn about traditional Omani farming techniques and organic practices.',
            'description_ar' => 'تعلم عن تقنيات الزراعة العمانية التقليدية والممارسات العضوية.',
            'instructor_name' => 'Ahmed Al-Kindi',
            'image_url' => '/images/workshops/farming.jpg',
            'duration_minutes' => 120,
            'max_participants' => 20,
            'price_omr' => 7.000,
            'skill_level' => 'all',
            'is_active' => true
        ]);

        // Add photo spots for Agricultural Village
        PhotoSpot::create([
            'heritage_village_id' => $agriculturalVillage->id,
            'name_en' => 'Date Palm Grove',
            'name_ar' => 'بستان النخيل',
            'description_en' => 'Beautiful palm grove perfect for photography.',
            'description_ar' => 'بستان نخيل جميل مثالي للتصوير.',
            'image_url' => '/images/spots/palm-grove.jpg',
            'best_time_for_photos' => 'Early morning (7:00 AM - 9:00 AM)',
            'is_active' => true
        ]);

        // Bedouin Heritage Village
        $bedouinVillage = HeritageVillage::create([
            'name_en' => 'Bedouin Heritage Village',
            'name_ar' => 'قرية التراث البدوي',
            'description_en' => 'Experience the nomadic desert life, traditional hospitality, and Bedouin crafts.',
            'description_ar' => 'عش تجربة الحياة البدوية الصحراوية والضيافة التقليدية والحرف البدوية.',
            'type' => 'bedouin',
            'cover_image' => 'https://example.com/bedouin-village.jpg',
            'opening_hours' => '9:00 AM - 10:00 PM',
            'is_active' => true
        ]);

        // Add attractions for Bedouin Village
        $bedouinAttractions = [
            ['en' => 'Traditional Bedouin Tents', 'ar' => 'الخيام البدوية التقليدية'],
            ['en' => 'Camel Riding Experience', 'ar' => 'تجربة ركوب الجمال'],
            ['en' => 'Traditional Coffee Ceremony', 'ar' => 'مراسم القهوة التقليدية'],
            ['en' => 'Desert Life Exhibition', 'ar' => 'معرض الحياة الصحراوية'],
            ['en' => 'Bedouin Handicrafts Market', 'ar' => 'سوق الحرف اليدوية البدوية'],
        ];

        foreach ($bedouinAttractions as $attraction) {
            VillageAttraction::create([
                'heritage_village_id' => $bedouinVillage->id,
                'name_en' => $attraction['en'],
                'name_ar' => $attraction['ar'],
                'description_en' => 'Experience the authentic ' . strtolower($attraction['en']) . ' of traditional Bedouin culture.',
                'description_ar' => 'عش تجربة ' . $attraction['ar'] . ' الأصيلة في الثقافة البدوية التقليدية.',
                'visiting_hours' => '9:00 AM - 9:00 PM',
                'is_active' => true
            ]);
        }

        // Add craft demonstrations for Bedouin Village
        CraftDemonstration::create([
            'heritage_village_id' => $bedouinVillage->id,
            'craft_name_en' => 'Traditional Tent Making',
            'craft_name_ar' => 'صناعة الخيام التقليدية',
            'description_en' => 'See how Bedouin tents are traditionally made from goat hair.',
            'description_ar' => 'شاهد كيف تُصنع الخيام البدوية تقليدياً من شعر الماعز.',
            'artisan_name' => 'Khalifa Al-Wahaibi',
            'demonstration_times' => '10:00 AM, 3:00 PM, 7:00 PM',
            'duration_minutes' => 50,
            'can_try_hands_on' => false,
            'is_active' => true
        ]);

        CraftDemonstration::create([
            'heritage_village_id' => $bedouinVillage->id,
            'craft_name_en' => 'Traditional Leather Working',
            'craft_name_ar' => 'صناعة الجلود التقليدية',
            'description_en' => 'Watch craftsmen create traditional leather goods.',
            'description_ar' => 'شاهد الحرفيين وهم يصنعون المنتجات الجلدية التقليدية.',
            'artisan_name' => 'Salem Al-Rashdi',
            'demonstration_times' => '11:00 AM, 4:00 PM, 8:00 PM',
            'duration_minutes' => 40,
            'can_try_hands_on' => true,
            'is_active' => true
        ]);

        // Add traditional activities for Bedouin Village
        TraditionalActivity::create([
            'heritage_village_id' => $bedouinVillage->id,
            'activity_name_en' => 'Camel Riding Experience',
            'activity_name_ar' => 'تجربة ركوب الجمال',
            'description_en' => 'Enjoy a traditional camel ride around the village.',
            'description_ar' => 'استمتع بركوب الجمال التقليدي حول القرية.',
            'image_url' => '/images/activities/camel-riding.jpg',
            'is_interactive' => true,
            'age_recommendation' => '5+',
            'timing' => 'Every 30 minutes',
            'is_active' => true
        ]);

        TraditionalActivity::create([
            'heritage_village_id' => $bedouinVillage->id,
            'activity_name_en' => 'Arabic Coffee Ceremony',
            'activity_name_ar' => 'مراسم القهوة العربية',
            'description_en' => 'Experience traditional Omani hospitality with coffee and dates.',
            'description_ar' => 'عش تجربة الضيافة العمانية التقليدية مع القهوة والتمر.',
            'image_url' => '/images/activities/coffee-ceremony.jpg',
            'is_interactive' => true,
            'age_recommendation' => 'All ages',
            'timing' => 'Every hour',
            'is_active' => true
        ]);

        echo "Heritage Villages seeded successfully!\n";
    }
}