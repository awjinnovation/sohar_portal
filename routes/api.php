<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapLocationController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PointsController;
use App\Http\Controllers\Api\PrivacyPolicyController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\TenantConfigController;
use App\Http\Controllers\Api\TermsAndConditionController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketValidationController;
use App\Http\Controllers\Api\TopupController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  (/api/v1, every request resolved to a tenant via X-Tenant)
|--------------------------------------------------------------------------
| See docs/platform_contract.md. ResolveTenant runs for the whole api group
| (bootstrap/app.php); unknown/inactive tenants get a 404.
*/

// Public routes - No authentication required
Route::prefix('v1')->group(function () {
    // Tenant config (contract §5) — public, cacheable, ETag
    Route::get('/tenant/config', [TenantConfigController::class, 'show']);

    // Legacy app configuration (reads from the tenant)
    Route::get('/config', [AppConfigController::class, 'index']);
    Route::get('/config/theme', [AppConfigController::class, 'theme']);
    Route::get('/config/branding', [AppConfigController::class, 'branding']);
    Route::get('/config/features', [AppConfigController::class, 'features']);
    Route::get('/config/status', [AppConfigController::class, 'status']);
    Route::get('/config/{key}', [AppConfigController::class, 'show']);

    // OTP Authentication (throttled)
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:otp-send');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');

    // Events
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/upcoming', [EventController::class, 'upcoming']);
    Route::get('/events/today', [EventController::class, 'today']);
    Route::get('/events/featured', [EventController::class, 'featured']);
    Route::get('/events/search', [EventController::class, 'index']);
    Route::get('/events/category/{categoryId}', [EventController::class, 'byCategory']);
    Route::get('/events/{id}/availability', [EventController::class, 'checkAvailability']);
    Route::get('/events/{id}/daily-availability', [EventController::class, 'dailyAvailability']);
    Route::get('/events/{id}', [EventController::class, 'show']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Restaurants (listings)
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::get('/restaurants/featured', [RestaurantController::class, 'featured']);
    Route::get('/restaurants/open-now', [RestaurantController::class, 'openNow']);
    Route::get('/restaurants/search', [RestaurantController::class, 'search']);
    Route::get('/restaurants/by-cuisine', [RestaurantController::class, 'byCuisine']);
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);

    // Map Locations (for events and venues)
    Route::get('/map-locations/types', [MapLocationController::class, 'types']);
    Route::get('/map-locations/entertainment', [MapLocationController::class, 'entertainment']);
    Route::get('/map-locations/food', [MapLocationController::class, 'food']);
    Route::get('/map-locations/facilities', [MapLocationController::class, 'facilities']);
    Route::get('/map-locations/parking', [MapLocationController::class, 'parking']);
    Route::get('/map-locations/category/{category}', [MapLocationController::class, 'byCategory']);
    Route::get('/map-locations', [MapLocationController::class, 'index']);

    // Locations
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

    // App Settings (public settings only)
    Route::get('/app-settings', [AppSettingController::class, 'index']);
    Route::get('/app-settings/{key}', [AppSettingController::class, 'show']);

    // Tickets
    Route::get('/tickets/pricing', [TicketController::class, 'pricing']);
    Route::get('/tickets/available', [TicketController::class, 'available']);

    // Price breakdown (server-computed; auth optional for points balance check)
    Route::get('/payments/quote', [PaymentController::class, 'quote']);

    // Legal
    Route::get('/privacy-policy', [PrivacyPolicyController::class, 'index']);
    Route::get('/privacy-policy/{language}', [PrivacyPolicyController::class, 'show']);
    Route::get('/terms-and-conditions', [TermsAndConditionController::class, 'index']);
    Route::get('/terms-and-conditions/{type}/{language}', [TermsAndConditionController::class, 'show']);

    // Places to visit + custom map, content collections and pages
    // (docs/places_content_contract.md §3). Published rows only.
    Route::prefix('places')->middleware('module:places')->group(function () {
        Route::get('/categories', [\App\Http\Controllers\Api\PlaceController::class, 'categories']);
        Route::get('/map', [\App\Http\Controllers\Api\PlaceController::class, 'map']);
        Route::get('/', [\App\Http\Controllers\Api\PlaceController::class, 'index']);
        Route::get('/{place}', [\App\Http\Controllers\Api\PlaceController::class, 'show']);
    });
    Route::prefix('content')->middleware('module:content')->group(function () {
        Route::get('/collections', [\App\Http\Controllers\Api\ContentController::class, 'collections']);
        Route::get('/collections/{key}/items', [\App\Http\Controllers\Api\ContentController::class, 'collectionItems']);
        Route::get('/items/{id}', [\App\Http\Controllers\Api\ContentController::class, 'item']);
        Route::get('/pages', [\App\Http\Controllers\Api\ContentController::class, 'pages']);
        Route::get('/pages/{slug}', [\App\Http\Controllers\Api\ContentController::class, 'page']);
    });

    // Bank Muscat server-to-server callback (verified by decryption)
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
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

    // QR Code Validation - gate staff of the current tenant
    Route::prefix('tickets')->middleware(['staff', 'module:staff_scanner'])->group(function () {
        Route::post('/validate', [TicketValidationController::class, 'validateQrCode']);
        Route::post('/{ticket}/mark-used', [TicketValidationController::class, 'markAsUsed']);
        Route::get('/validation-history', [TicketValidationController::class, 'validationHistory']);
    });

    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id');
    Route::get('/tickets/{id}/qr-code', [TicketController::class, 'getQrCode'])->whereNumber('id');
    Route::post('/tickets/{id}/transfer', [TicketController::class, 'transfer'])->middleware('module:ticket_transfer');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'myNotifications']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Payments / checkout (contract §8) — always scoped to the owner
    Route::post('/payments/initialize', [PaymentController::class, 'initialize'])->middleware('throttle:money');
    Route::post('/payments/confirm', [PaymentController::class, 'confirm']);
    Route::post('/payments/cancel', [PaymentController::class, 'cancel']);
    Route::get('/payments/status/{transactionId}', [PaymentController::class, 'checkStatus']);
    Route::get('/payments/history', [PaymentController::class, 'myTransactions']);
    Route::get('/payments/{id}', [PaymentController::class, 'show'])->whereNumber('id');

    // Wallet (contract §6)
    Route::prefix('wallet')->middleware('module:wallet')->group(function () {
        Route::get('/', [WalletController::class, 'show']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::post('/topups', [TopupController::class, 'store'])->middleware(['module:topup', 'throttle:money']);
        Route::get('/topups/{id}', [TopupController::class, 'show'])->whereNumber('id');
        Route::post('/pay-token', [WalletController::class, 'payToken'])->middleware('throttle:money');
        Route::get('/deposits', [WalletController::class, 'deposits'])->middleware('module:deposits');
        Route::get('/refund-requests', [WalletController::class, 'refundRequests'])->middleware('module:refunds');
        Route::post('/refund-requests', [WalletController::class, 'storeRefundRequest'])->middleware(['module:refunds', 'throttle:money']);
    });

    // Points (contract §7)
    Route::prefix('points')->middleware('module:points')->group(function () {
        Route::get('/', [PointsController::class, 'show']);
        Route::get('/history', [PointsController::class, 'history']);
        Route::post('/redeem', [PointsController::class, 'redeem'])->middleware('throttle:money');
    });

    // Merchant / staff (contract §9)
    Route::prefix('merchant')->middleware(['merchant', 'module:vendors,deposits'])->group(function () {
        Route::get('/me', [MerchantController::class, 'me']);
        Route::post('/charge', [MerchantController::class, 'charge']);
        Route::post('/deposits', [MerchantController::class, 'deposit']);
        Route::post('/deposits/{id}/release', [MerchantController::class, 'releaseDeposit'])->whereNumber('id');
        Route::get('/transactions', [MerchantController::class, 'transactions']);
    });

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::get('/favorites/events', [FavoriteController::class, 'events']);
    Route::get('/favorites/restaurants', [FavoriteController::class, 'restaurants']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);
});

// Gate scanner (docs/ai_and_gate_contract.md §B2) — authenticated as a paired
// GateDevice (Sanctum token, ability `gate`), not as a user.
Route::prefix('v1/gate')->group(function () {
    Route::post('/pair', [\App\Http\Controllers\Api\GateController::class, 'pair'])->middleware('throttle:gate-pair');

    Route::middleware([\App\Http\Middleware\AuthenticateGateDevice::class, 'module:staff_scanner'])->group(function () {
        Route::post('/scan', [\App\Http\Controllers\Api\GateController::class, 'scan'])->middleware('throttle:gate-scan');
        Route::post('/sync', [\App\Http\Controllers\Api\GateController::class, 'sync'])->middleware('throttle:gate-sync');
        Route::post('/staff/login', [\App\Http\Controllers\Api\GateController::class, 'staffLogin'])->middleware('throttle:gate-staff-login');
        Route::middleware('throttle:gate-api')->group(function () {
            Route::get('/session', [\App\Http\Controllers\Api\GateController::class, 'session']);
            Route::post('/staff/logout', [\App\Http\Controllers\Api\GateController::class, 'staffLogout']);
            Route::get('/manifest', [\App\Http\Controllers\Api\GateController::class, 'manifest']);
            Route::get('/stats', [\App\Http\Controllers\Api\GateController::class, 'stats']);
            Route::post('/heartbeat', [\App\Http\Controllers\Api\GateController::class, 'heartbeat']);
        });

        // Box office (docs/gate_sales_contract.md §3): staff sell tickets and
        // send the visitor a payment link. Needs gate_sales.enabled, a gate
        // with can_sell and a signed-in staff member with `sell tickets`.
        Route::prefix('sales')->middleware(\App\Http\Middleware\EnsureGateSales::class)->group(function () {
            $c = \App\Http\Controllers\Api\GateSalesController::class;
            Route::middleware('throttle:gate-sales')->group(function () use ($c) {
                Route::get('/catalog', [$c, 'catalog']);
                Route::post('/quote', [$c, 'quote']);
                Route::get('/orders', [$c, 'index']);
                Route::get('/orders/{id}', [$c, 'show'])->whereNumber('id');
            });
            Route::middleware('throttle:gate-sales-write')->group(function () use ($c) {
                Route::post('/orders', [$c, 'store']);
                Route::post('/orders/{id}/sms', [$c, 'sms'])->whereNumber('id');
                Route::post('/orders/{id}/cancel', [$c, 'cancel'])->whereNumber('id');
                Route::post('/orders/{id}/admit', [$c, 'admit'])->whereNumber('id');
            });
        });
    });
});

// AI Experience (docs/ai_and_gate_contract.md §A3–A4) — auth optional:
// Sanctum bearer token or the app's opaque X-Ai-Guest header. Module checks
// (ai_assistant / ai_plan → AI_DISABLED), rate limits and the tenant token
// budget are enforced in the controllers.
Route::prefix('v1/ai')->group(function () {
    Route::post('/chat', [\App\Http\Controllers\Api\AiChatController::class, 'chat']);
    Route::post('/messages/{id}/feedback', [\App\Http\Controllers\Api\AiChatController::class, 'feedback'])->whereNumber('id')->middleware('throttle:60,1');
    Route::get('/conversations', [\App\Http\Controllers\Api\AiChatController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/conversations/{id}', [\App\Http\Controllers\Api\AiChatController::class, 'show'])->middleware('throttle:60,1');
    Route::delete('/conversations/{id}', [\App\Http\Controllers\Api\AiChatController::class, 'destroy'])->middleware('throttle:60,1');
    Route::post('/plan', [\App\Http\Controllers\Api\AiPlanController::class, 'store']);
    Route::get('/plans/{id}', [\App\Http\Controllers\Api\AiPlanController::class, 'show'])->middleware('throttle:60,1');
});
