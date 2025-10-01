#!/bin/bash

# Create all API controllers

# TicketController
cat > app/Http/Controllers/Api/TicketController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketController extends Controller
{
    public function available()
    {
        $tickets = TicketPricing::where('is_available', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    public function pricing()
    {
        $pricing = TicketPricing::all();

        return response()->json([
            'success' => true,
            'data' => $pricing
        ]);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'ticket_type' => 'required|string',
            'quantity' => 'required|integer|min:1|max:10',
            'date' => 'required|date'
        ]);

        $pricing = TicketPricing::where('ticket_type', $request->ticket_type)
            ->where('is_available', true)
            ->first();

        if (!$pricing) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket type not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'price_per_ticket' => $pricing->price,
            'total_price' => $pricing->price * $request->quantity
        ]);
    }

    public function purchase(Request $request)
    {
        // This will be handled through PaymentController
        return response()->json([
            'success' => false,
            'message' => 'Please use /payments/initialize endpoint for ticket purchase'
        ]);
    }

    public function myTickets(Request $request)
    {
        $tickets = Ticket::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total()
            ]
        ]);
    }

    public function show($id)
    {
        $ticket = Ticket::where('user_id', request()->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    public function getQrCode($id)
    {
        $ticket = Ticket::where('user_id', request()->user()->id)
            ->findOrFail($id);

        $qrCode = base64_encode(QrCode::format('png')->size(300)->generate($ticket->qr_code));

        return response()->json([
            'success' => true,
            'qr_code' => 'data:image/png;base64,' . $qrCode,
            'ticket_number' => $ticket->ticket_number
        ]);
    }
}
EOF

# RestaurantController
cat > app/Http/Controllers/Api/RestaurantController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\UserFavorite;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index(Request $request)
    {
        $query = Restaurant::with(['images', 'features', 'openingHours']);

        if ($request->has('cuisine')) {
            $query->where('cuisine_type', $request->cuisine);
        }

        if ($request->has('is_vegetarian')) {
            $query->where('is_vegetarian', $request->is_vegetarian);
        }

        if ($request->has('is_family_friendly')) {
            $query->where('is_family_friendly', $request->is_family_friendly);
        }

        $restaurants = $query->where('status', 'active')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $restaurants->items(),
            'pagination' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total()
            ]
        ]);
    }

    public function show($id)
    {
        $restaurant = Restaurant::with(['images', 'features', 'openingHours'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $restaurant
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $restaurants = Restaurant::with(['images', 'features'])
            ->where('status', 'active')
            ->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query . '%')
                  ->orWhere('description', 'like', '%' . $request->query . '%')
                  ->orWhere('cuisine_type', 'like', '%' . $request->query . '%');
            })
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $restaurants->items(),
            'pagination' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total()
            ]
        ]);
    }

    public function toggleFavorite(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $user = $request->user();

        $favorite = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_id', $restaurant->id)
            ->where('favoritable_type', Restaurant::class)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $isFavorite = false;
        } else {
            UserFavorite::create([
                'user_id' => $user->id,
                'favoritable_id' => $restaurant->id,
                'favoritable_type' => Restaurant::class
            ]);
            $isFavorite = true;
        }

        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorite,
            'message' => $isFavorite ? 'Added to favorites' : 'Removed from favorites'
        ]);
    }

    public function favorites(Request $request)
    {
        $user = $request->user();

        $favoriteIds = UserFavorite::where('user_id', $user->id)
            ->where('favoritable_type', Restaurant::class)
            ->pluck('favoritable_id');

        $restaurants = Restaurant::with(['images', 'features'])
            ->whereIn('id', $favoriteIds)
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $restaurants->items(),
            'pagination' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total()
            ]
        ]);
    }
}
EOF

# HeritageVillageController
cat > app/Http/Controllers/Api/HeritageVillageController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeritageVillage;
use Illuminate\Http\Request;

class HeritageVillageController extends Controller
{
    public function index()
    {
        $villages = HeritageVillage::with(['images', 'attractions'])
            ->where('status', 'active')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $villages
        ]);
    }

    public function show($id)
    {
        $village = HeritageVillage::with(['images', 'attractions'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $village
        ]);
    }
}
EOF

# PhotoSpotController
cat > app/Http/Controllers/Api/PhotoSpotController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotoSpot;
use Illuminate\Http\Request;

class PhotoSpotController extends Controller
{
    public function index()
    {
        $spots = PhotoSpot::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $spots
        ]);
    }

    public function show($id)
    {
        $spot = PhotoSpot::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $spot
        ]);
    }
}
EOF

# VillageAttractionController
cat > app/Http/Controllers/Api/VillageAttractionController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VillageAttraction;
use Illuminate\Http\Request;

class VillageAttractionController extends Controller
{
    public function index()
    {
        $attractions = VillageAttraction::with('village')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attractions
        ]);
    }

    public function show($id)
    {
        $attraction = VillageAttraction::with('village')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $attraction
        ]);
    }

    public function byVillage($villageId)
    {
        $attractions = VillageAttraction::where('village_id', $villageId)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attractions
        ]);
    }
}
EOF

# CulturalWorkshopController
cat > app/Http/Controllers/Api/CulturalWorkshopController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CulturalWorkshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CulturalWorkshopController extends Controller
{
    public function index()
    {
        $workshops = CulturalWorkshop::where('status', 'active')
            ->where('workshop_date', '>=', now())
            ->orderBy('workshop_date')
            ->orderBy('workshop_time')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $workshops->items(),
            'pagination' => [
                'current_page' => $workshops->currentPage(),
                'last_page' => $workshops->lastPage(),
                'per_page' => $workshops->perPage(),
                'total' => $workshops->total()
            ]
        ]);
    }

    public function show($id)
    {
        $workshop = CulturalWorkshop::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $workshop
        ]);
    }

    public function available()
    {
        $workshops = CulturalWorkshop::where('status', 'active')
            ->where('workshop_date', '>=', now())
            ->whereColumn('current_participants', '<', 'max_participants')
            ->orderBy('workshop_date')
            ->orderBy('workshop_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workshops
        ]);
    }

    public function register(Request $request, $id)
    {
        $workshop = CulturalWorkshop::findOrFail($id);

        if ($workshop->current_participants >= $workshop->max_participants) {
            return response()->json([
                'success' => false,
                'message' => 'Workshop is fully booked'
            ], 400);
        }

        // Check if already registered
        $existing = DB::table('workshop_registrations')
            ->where('user_id', $request->user()->id)
            ->where('workshop_id', $id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Already registered for this workshop'
            ], 400);
        }

        // Register user
        DB::table('workshop_registrations')->insert([
            'user_id' => $request->user()->id,
            'workshop_id' => $id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Increment participants count
        $workshop->increment('current_participants');

        return response()->json([
            'success' => true,
            'message' => 'Successfully registered for workshop'
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $deleted = DB::table('workshop_registrations')
            ->where('user_id', $request->user()->id)
            ->where('workshop_id', $id)
            ->delete();

        if ($deleted) {
            CulturalWorkshop::find($id)->decrement('current_participants');

            return response()->json([
                'success' => true,
                'message' => 'Registration cancelled successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Registration not found'
        ], 404);
    }

    public function myRegistrations(Request $request)
    {
        $workshopIds = DB::table('workshop_registrations')
            ->where('user_id', $request->user()->id)
            ->pluck('workshop_id');

        $workshops = CulturalWorkshop::whereIn('id', $workshopIds)
            ->orderBy('workshop_date')
            ->orderBy('workshop_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workshops
        ]);
    }
}
EOF

# TraditionalActivityController
cat > app/Http/Controllers/Api/TraditionalActivityController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TraditionalActivity;
use Illuminate\Http\Request;

class TraditionalActivityController extends Controller
{
    public function index()
    {
        $activities = TraditionalActivity::where('is_active', true)
            ->orderBy('activity_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    public function show($id)
    {
        $activity = TraditionalActivity::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $activity
        ]);
    }
}
EOF

# CraftDemonstrationController
cat > app/Http/Controllers/Api/CraftDemonstrationController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CraftDemonstration;
use Illuminate\Http\Request;

class CraftDemonstrationController extends Controller
{
    public function index()
    {
        $demonstrations = CraftDemonstration::where('is_active', true)
            ->orderBy('demonstration_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $demonstrations
        ]);
    }

    public function show($id)
    {
        $demonstration = CraftDemonstration::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $demonstration
        ]);
    }
}
EOF

# EmergencyContactController
cat > app/Http/Controllers/Api/EmergencyContactController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;

class EmergencyContactController extends Controller
{
    public function index()
    {
        $contacts = EmergencyContact::where('is_active', true)
            ->orderBy('priority')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }
}
EOF

# NotificationController
cat > app/Http/Controllers/Api/NotificationController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function public()
    {
        $notifications = Notification::where('is_public', true)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public function myNotifications(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total()
            ]
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count
        ]);
    }
}
EOF

# MapLocationController
cat > app/Http/Controllers/Api/MapLocationController.php << 'EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapLocation;
use Illuminate\Http\Request;

class MapLocationController extends Controller
{
    public function index()
    {
        $locations = MapLocation::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    public function byCategory($category)
    {
        $locations = MapLocation::where('category', $category)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }
}
EOF

echo "All API controllers created successfully!"