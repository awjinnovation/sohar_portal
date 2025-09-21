<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\CraftDemonstration;
use App\Models\CraftDemonstrationSchedule;
use App\Models\CulturalTimelineEvent;
use App\Models\CulturalWorkshop;
use App\Models\EmergencyContact;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\FirstAidStation;
use App\Models\HealthTip;
use App\Models\HeritageVillage;
use App\Models\LocationCategory;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\OtpVerification;
use App\Models\Payment;
use App\Models\PhotographyTip;
use App\Models\PhotoSpot;
use App\Models\Restaurant;
use App\Models\RestaurantFeature;
use App\Models\RestaurantImage;
use App\Models\RestaurantOpeningHour;
use App\Models\Ticket;
use App\Models\TicketPricing;
use App\Models\TraditionalActivity;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserInterest;
use App\Models\VillageAttraction;
use App\Models\VillageImage;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopSchedule;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'main' => [
                'users' => User::count(),
                'events' => Event::count(),
                'tickets' => Ticket::count(),
                'payments' => Payment::count(),
            ],
            'festival' => [
                'categories' => Category::count(),
                'event_tags' => EventTag::count(),
                'ticket_pricing' => TicketPricing::count(),
                'restaurants' => Restaurant::count(),
                'restaurant_features' => RestaurantFeature::count(),
                'restaurant_images' => RestaurantImage::count(),
                'restaurant_opening_hours' => RestaurantOpeningHour::count(),
            ],
            'heritage' => [
                'heritage_villages' => HeritageVillage::count(),
                'village_images' => VillageImage::count(),
                'village_attractions' => VillageAttraction::count(),
                'craft_demonstrations' => CraftDemonstration::count(),
                'craft_demonstration_schedules' => CraftDemonstrationSchedule::count(),
                'traditional_activities' => TraditionalActivity::count(),
                'cultural_workshops' => CulturalWorkshop::count(),
                'workshop_registrations' => WorkshopRegistration::count(),
                'workshop_schedules' => WorkshopSchedule::count(),
                'cultural_timeline_events' => CulturalTimelineEvent::count(),
                'photo_spots' => PhotoSpot::count(),
                'photography_tips' => PhotographyTip::count(),
            ],
            'communication' => [
                'announcements' => Announcement::count(),
                'notifications' => Notification::count(),
                'emergency_contacts' => EmergencyContact::count(),
            ],
            'locations' => [
                'map_locations' => MapLocation::count(),
                'location_categories' => LocationCategory::count(),
                'first_aid_stations' => FirstAidStation::count(),
                'health_tips' => HealthTip::count(),
            ],
            'user_data' => [
                'user_favorites' => UserFavorite::count(),
                'user_interests' => UserInterest::count(),
                'otp_verifications' => OtpVerification::count(),
            ],
            'settings' => [
                'app_settings' => AppSetting::count(),
            ]
        ];

        $enabledControllers = $this->getEnabledControllers();

        return view('admin.dashboard', compact('stats', 'enabledControllers'));
    }

    private function getEnabledControllers()
    {
        $controllers = [];
        $adminPath = app_path('Http/Controllers/Admin');

        if (is_dir($adminPath)) {
            $files = scandir($adminPath);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $controllerName = str_replace('Controller.php', '', $file);
                    $controllers[] = $controllerName;
                }
            }
        }

        return $controllers;
    }
}