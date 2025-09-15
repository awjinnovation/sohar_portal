<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\HeritageVillageController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\PhotoSpotController;
use App\Http\Controllers\Api\VillageAttractionController;
use App\Http\Controllers\Api\CulturalWorkshopController;
use App\Http\Controllers\Api\TraditionalActivityController;
use App\Http\Controllers\Api\CraftDemonstrationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\EmergencyContactController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MapLocationController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes - No authentication required
Route::prefix('v1')->group(function () {
    // OTP Authentication
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

    // Public endpoints for viewing content
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/upcoming', [EventController::class, 'upcoming']);
    Route::get('/events/today', [EventController::class, 'today']);
    Route::get('/events/featured', [EventController::class, 'featured']);
    Route::get('/events/category/{categoryId}', [EventController::class, 'byCategory']);
    Route::get('/events/{id}', [EventController::class, 'show']);

    Route::get('/heritage-villages', [HeritageVillageController::class, 'index']);
    Route::get('/heritage-villages/{id}', [HeritageVillageController::class, 'show']);

    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::get('/restaurants/open-now', [RestaurantController::class, 'openNow']);
    Route::get('/restaurants/search', [RestaurantController::class, 'search']);
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);

    Route::get('/photo-spots', [PhotoSpotController::class, 'index']);
    Route::get('/photo-spots/{id}', [PhotoSpotController::class, 'show']);

    Route::get('/village-attractions', [VillageAttractionController::class, 'index']);
    Route::get('/village-attractions/village/{villageId}', [VillageAttractionController::class, 'byVillage']);
    Route::get('/village-attractions/{id}', [VillageAttractionController::class, 'show']);

    Route::get('/cultural-workshops', [CulturalWorkshopController::class, 'index']);
    Route::get('/cultural-workshops/available', [CulturalWorkshopController::class, 'available']);
    Route::get('/cultural-workshops/{id}', [CulturalWorkshopController::class, 'show']);

    Route::get('/traditional-activities', [TraditionalActivityController::class, 'index']);
    Route::get('/traditional-activities/interactive', [TraditionalActivityController::class, 'interactive']);
    Route::get('/traditional-activities/{id}', [TraditionalActivityController::class, 'show']);

    Route::get('/craft-demonstrations', [CraftDemonstrationController::class, 'index']);
    Route::get('/craft-demonstrations/live', [CraftDemonstrationController::class, 'live']);
    Route::get('/craft-demonstrations/{id}', [CraftDemonstrationController::class, 'show']);

    Route::get('/emergency-contacts', [EmergencyContactController::class, 'index']);

    Route::get('/map-locations', [MapLocationController::class, 'index']);
    Route::get('/map-locations/entertainment', [MapLocationController::class, 'entertainment']);
    Route::get('/map-locations/food', [MapLocationController::class, 'food']);
    Route::get('/map-locations/facilities', [MapLocationController::class, 'facilities']);
    Route::get('/map-locations/parking', [MapLocationController::class, 'parking']);
    Route::get('/map-locations/category/{category}', [MapLocationController::class, 'byCategory']);

    Route::get('/notifications/public', [NotificationController::class, 'public']);

    // Announcements
    Route::get('/announcements', [\App\Http\Controllers\Api\AnnouncementController::class, 'index']);
    Route::get('/announcements/active', [\App\Http\Controllers\Api\AnnouncementController::class, 'active']);

    // App Settings
    Route::get('/app-settings', [\App\Http\Controllers\Api\AppSettingController::class, 'index']);

    // Public ticket pricing
    Route::get('/tickets/pricing', [TicketController::class, 'pricing']);
});

// Protected routes - Authentication required
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::post('/auth/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);

    // Ticket purchasing and management
    Route::get('/tickets/available', [TicketController::class, 'available']);
    Route::post('/tickets/check-availability', [TicketController::class, 'checkAvailability']);
    Route::post('/tickets/purchase', [TicketController::class, 'purchase']);
    Route::get('/tickets/my-tickets', [TicketController::class, 'myTickets']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::get('/tickets/{id}/qr-code', [TicketController::class, 'getQrCode']);

    // Workshop registrations
    Route::post('/cultural-workshops/{id}/register', [CulturalWorkshopController::class, 'register']);
    Route::delete('/cultural-workshops/{id}/cancel', [CulturalWorkshopController::class, 'cancel']);
    Route::get('/cultural-workshops/my-registrations', [CulturalWorkshopController::class, 'myRegistrations']);

    // User notifications
    Route::get('/notifications/my-notifications', [NotificationController::class, 'myNotifications']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // Payment endpoints
    Route::post('/payments/initialize', [PaymentController::class, 'initialize']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
    Route::get('/payments/status/{sessionId}', [PaymentController::class, 'checkStatus']);
    Route::post('/payments/webhook', [PaymentController::class, 'webhook'])->withoutMiddleware('auth:sanctum');
    Route::get('/payments/my-transactions', [PaymentController::class, 'myTransactions']);

    // Favorites/Bookmarks
    Route::get('/events/favorites', [EventController::class, 'favorites']);
    Route::post('/events/{id}/favorite', [EventController::class, 'toggleFavorite']);

    Route::get('/restaurants/favorites', [RestaurantController::class, 'favorites']);
    Route::post('/restaurants/{id}/favorite', [RestaurantController::class, 'toggleFavorite']);
});