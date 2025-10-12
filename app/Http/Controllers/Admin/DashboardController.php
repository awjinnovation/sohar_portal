<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\OtpVerification;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserFavorite;
use App\Models\UserInterest;

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
                'restaurants' => Restaurant::count(),
            ],
            'locations' => [
                'all_locations' => MapLocation::count(),
                'emergency' => MapLocation::where('type', 'emergency')->count(),
                'services' => MapLocation::whereIn('type', ['parking', 'restroom', 'facilities'])->count(),
                'first_aid' => MapLocation::where('type', 'first_aid')->count(),
            ],
            'communication' => [
                'announcements' => Announcement::count(),
                'notifications' => Notification::count(),
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