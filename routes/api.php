<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketValidationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\PrivacyPolicyController;
use App\Http\Controllers\Api\TermsAndConditionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes - No authentication required
Route::prefix('v1')->group(function () {
    // App Configuration - For Flutter App
    Route::get('/config', [AppConfigController::class, 'index']);
    Route::get('/config/theme', [AppConfigController::class, 'theme']);
    Route::get('/config/branding', [AppConfigController::class, 'branding']);
    Route::get('/config/features', [AppConfigController::class, 'features']);
    Route::get('/config/status', [AppConfigController::class, 'status']);
    Route::get('/config/{key}', [AppConfigController::class, 'show']);

    // OTP Authentication
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);

    // Events
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/upcoming', [EventController::class, 'upcoming']);
    Route::get('/events/today', [EventController::class, 'today']);
    Route::get('/events/featured', [EventController::class, 'featured']);
    Route::get('/events/search', [EventController::class, 'search']);
    Route::get('/events/category/{categoryId}', [EventController::class, 'byCategory']);
    Route::get('/events/{id}/availability', [EventController::class, 'checkAvailability']);
    Route::get('/events/{id}/daily-availability', [EventController::class, 'dailyAvailability']);
    Route::get('/events/{id}', [EventController::class, 'show']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Restaurants
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::get('/restaurants/featured', [RestaurantController::class, 'featured']);
    Route::get('/restaurants/open-now', [RestaurantController::class, 'openNow']);
    Route::get('/restaurants/search', [RestaurantController::class, 'search']);
    Route::get('/restaurants/by-cuisine', [RestaurantController::class, 'byCuisine']);
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);

    // Map Locations (for events and venues)
    Route::get('/map-locations/types', [\App\Http\Controllers\Api\MapLocationController::class, 'types']);
    Route::get('/map-locations/entertainment', [\App\Http\Controllers\Api\MapLocationController::class, 'entertainment']);
    Route::get('/map-locations/food', [\App\Http\Controllers\Api\MapLocationController::class, 'food']);
    Route::get('/map-locations/facilities', [\App\Http\Controllers\Api\MapLocationController::class, 'facilities']);
    Route::get('/map-locations/parking', [\App\Http\Controllers\Api\MapLocationController::class, 'parking']);
    Route::get('/map-locations/category/{category}', [\App\Http\Controllers\Api\MapLocationController::class, 'byCategory']);
    Route::get('/map-locations', [\App\Http\Controllers\Api\MapLocationController::class, 'index']);

    // Locations (unified locations table)
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations/type/{type}', [LocationController::class, 'byType']);
    Route::get('/locations/emergency', [LocationController::class, 'emergency']);
    Route::get('/locations/first-aid', [LocationController::class, 'firstAid']);
    Route::get('/locations/parking', [LocationController::class, 'parking']);
    Route::get('/locations/restrooms', [LocationController::class, 'restrooms']);
    Route::get('/locations/nearby', [LocationController::class, 'nearby']);
    Route::get('/locations/{id}', [LocationController::class, 'show']);

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/active', [AnnouncementController::class, 'active']);
    Route::get('/announcements/priority', [AnnouncementController::class, 'priority']);
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);

    // Notifications (public)
    Route::get('/notifications/public', [NotificationController::class, 'public']);

    // App Settings
    Route::get('/app-settings', [AppSettingController::class, 'index']);
    Route::get('/app-settings/{key}', [AppSettingController::class, 'show']);

    // Tickets
    Route::get('/tickets/pricing', [TicketController::class, 'pricing']);
    Route::get('/tickets/available', [TicketController::class, 'available']);

    // Legal
    Route::get('/privacy-policy', [PrivacyPolicyController::class, 'index']);
    Route::get('/privacy-policy/{language}', [PrivacyPolicyController::class, 'show']);
    Route::get('/terms-and-conditions', [TermsAndConditionController::class, 'index']);
    Route::get('/terms-and-conditions/{type}/{language}', [TermsAndConditionController::class, 'show']);

    // Payment cancel endpoint (called by Thawani)
    Route::post('/payments/cancel', [PaymentController::class, 'cancel']);
});

// Protected routes - Authentication required
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // User profile
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::post('/auth/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::delete('/auth/delete-account', [AuthController::class, 'deleteAccount']);

    // Tickets
    Route::post('/tickets/check-availability', [TicketController::class, 'checkAvailability']);
    Route::post('/tickets/purchase', [TicketController::class, 'purchase']);
    Route::get('/tickets/my-tickets', [TicketController::class, 'myTickets']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::get('/tickets/{id}/qr-code', [TicketController::class, 'getQrCode']);
    Route::post('/tickets/{id}/transfer', [TicketController::class, 'transfer']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Payments
    Route::post('/payments/initialize', [PaymentController::class, 'initialize']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
    Route::get('/payments/status/{sessionId}', [PaymentController::class, 'checkStatus']);
    Route::post('/payments/webhook', [PaymentController::class, 'webhook'])->withoutMiddleware('auth:sanctum');
    Route::get('/payments/history', [PaymentController::class, 'history']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::get('/favorites/events', [FavoriteController::class, 'events']);
    Route::get('/favorites/restaurants', [FavoriteController::class, 'restaurants']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);

    // QR Code Validation - Staff Only (requires special authentication)
    Route::prefix('tickets')->group(function () {
        Route::post('/validate', [TicketValidationController::class, 'validate']);
        Route::post('/{ticket}/mark-used', [TicketValidationController::class, 'markAsUsed']);
        Route::get('/validation-history', [TicketValidationController::class, 'validationHistory']);
    });
});