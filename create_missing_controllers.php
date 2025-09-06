<?php

// Create missing controllers
$controllers = [
    // Heritage Village Related
    'VillageImageController',
    'VillageAttractionController',
    'CraftDemonstrationController',
    'TraditionalActivityController',
    'CulturalWorkshopController',
    'PhotoSpotController',
    
    // Event Related
    'EventTagController',
    'TicketController',
    'TicketPricingController',
    
    // Restaurant Related
    'RestaurantImageController',
    'RestaurantOpeningHourController',
    'RestaurantFeatureController',
    
    // Map & Location
    'MapLocationController',
    'LocationCategoryController',
    
    // Others
    'CulturalTimelineEventController',
    'FirstAidStationController',
    'HealthTipController',
    'AppSettingController',
    'NotificationController'
];

foreach ($controllers as $controller) {
    $cmd = "php artisan make:controller Admin/{$controller} --resource";
    echo "Creating $controller...\n";
    exec($cmd);
}

echo "All controllers created!\n";