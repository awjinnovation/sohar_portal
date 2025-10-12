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
            [
                'type' => 'venue',
                'name' => 'Sohar Fort',
                'name_ar' => 'قلعة صحار',
                'description' => 'Historic fort and main festival venue',
                'description_ar' => 'قلعة تاريخية ومكان المهرجان الرئيسي',
                'latitude' => 24.3570,
                'longitude' => 56.7520,
                'icon' => 'fort',
                'color' => 4288423856,
                'is_active' => true
            ],
            [
                'type' => 'venue',
                'name' => 'Sohar Heritage Village',
                'name_ar' => 'قرية صحار التراثية',
                'description' => 'Traditional heritage village showcasing Omani culture',
                'description_ar' => 'قرية تراثية تقليدية تعرض الثقافة العمانية',
                'latitude' => 24.3652,
                'longitude' => 56.7443,
                'icon' => 'heritage',
                'color' => 4286141768,
                'is_active' => true
            ],
            [
                'type' => 'venue',
                'name' => 'Sohar Corniche',
                'name_ar' => 'كورنيش صحار',
                'description' => 'Beautiful waterfront promenade',
                'description_ar' => 'كورنيش جميل على الواجهة البحرية',
                'latitude' => 24.3623,
                'longitude' => 56.7502,
                'icon' => 'beach',
                'color' => 4280391411,
                'is_active' => true
            ],
            [
                'type' => 'parking',
                'name' => 'Main Parking Area',
                'name_ar' => 'موقف السيارات الرئيسي',
                'description' => 'Large parking facility near the fort',
                'description_ar' => 'موقف سيارات كبير بالقرب من القلعة',
                'latitude' => 24.3555,
                'longitude' => 56.7535,
                'icon' => 'parking',
                'color' => 4284513675,
                'is_active' => true
            ],
            [
                'type' => 'amenity',
                'name' => 'Festival Information Center',
                'name_ar' => 'مركز معلومات المهرجان',
                'description' => 'Get information and assistance',
                'description_ar' => 'احصل على المعلومات والمساعدة',
                'latitude' => 24.3580,
                'longitude' => 56.7510,
                'icon' => 'information',
                'color' => 4285887861,
                'is_active' => true
            ]
        ];

        foreach ($locations as $locationData) {
            MapLocation::create($locationData);
        }
    }
}
