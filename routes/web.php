<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Web\PaymentController;
use Illuminate\Support\Facades\Route;

// Product homepage (Mawsim landing page, ?lang=ar|en) and its demo form
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::post('/demo-request', [LandingController::class, 'store'])
    ->middleware('throttle:demo-requests')->name('landing.demo-request');
// robots.txt / sitemap.xml with absolute URLs from APP_URL (no session needed)
Route::get('/robots.txt', [LandingController::class, 'robots'])->withoutMiddleware('web')->name('landing.robots');
Route::get('/sitemap.xml', [LandingController::class, 'sitemap'])->withoutMiddleware('web')->name('landing.sitemap');

// Public Pages (tenant via ?tenant=<slug>, default tenant otherwise)
Route::get('/privacy-policy', [PublicPageController::class, 'privacyPolicy'])->name('public.privacy-policy');
Route::get('/terms-and-conditions', [PublicPageController::class, 'termsAndConditions'])->name('public.terms-and-conditions');
Route::get('/discover/places/{slug}', [PublicPageController::class, 'place'])->name('public.place');

// Payment Pages (opened by the app's web view)
Route::get('/payment/redirect/{transaction_id}', [PaymentController::class, 'redirectToGateway'])->middleware('signed')->name('payment.redirect');
Route::post('/payment/fake/{transaction_id}', [PaymentController::class, 'fakeComplete'])->middleware('signed')->name('payment.fake.complete');
Route::match(['get', 'post'], '/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::match(['get', 'post'], '/payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');
Route::get('/payment/download/{transactionId}', [PaymentController::class, 'downloadTicket'])->middleware('signed')->name('payment.download');
Route::get('/ticket/{qrCode}', [PaymentController::class, 'downloadSingleTicket'])->name('payment.ticket.single');

// Gate box office payment link (docs/gate_sales_contract.md §4) — public, the token is the secret.
Route::get('/pay/{token}', [\App\Http\Controllers\Web\BoxOfficePayController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')->middleware('throttle:pay-page')->name('box-office.pay');
Route::post('/pay/{token}/checkout', [\App\Http\Controllers\Web\BoxOfficePayController::class, 'checkout'])
    ->where('token', '[A-Za-z0-9]{32,64}')->middleware('throttle:pay-checkout')->name('box-office.pay.checkout');

// Fallback login route for Laravel's default auth middleware
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// Admin Routes
Route::prefix('admin')->middleware('admin.locale')->group(function () {
    // EN/AR switch (session), available on the login page too.
    Route::post('/locale', [AdminAuthController::class, 'switchLocale'])->name('admin.locale');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login.form');
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1')->name('admin.login');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::post('/switch-tenant', [Admin\TenantController::class, 'switch'])->name('admin.tenant.switch');

        // Media Management
        Route::post('/media/upload', [Admin\MediaController::class, 'upload'])->name('admin.media.upload');
        Route::delete('/media/delete', [Admin\MediaController::class, 'delete'])->name('admin.media.delete');

        // AI copilot (docs/ai_and_gate_contract.md §A5–A6) — JSON for page JS;
        // permissions are checked per endpoint. Registered before any
        // `gates/{gate}` resource route so /admin/gates/insights is not shadowed.
        Route::prefix('ai')->name('admin.ai.')->middleware('throttle:60,1')->group(function () {
            Route::post('copilot/event-description', [Admin\AiCopilotController::class, 'eventDescription'])->name('copilot.event-description');
            Route::post('copilot/translate', [Admin\AiCopilotController::class, 'translate'])->name('copilot.translate');
            Route::post('copilot/shorten', [Admin\AiCopilotController::class, 'shorten'])->name('copilot.shorten');
            Route::post('copilot/announcement', [Admin\AiCopilotController::class, 'announcement'])->name('copilot.announcement');
            Route::post('copilot/content-description', [Admin\AiCopilotController::class, 'contentDescription'])->name('copilot.content-description');
            Route::get('briefing', [Admin\AiCopilotController::class, 'briefing'])->name('briefing');
            Route::post('briefing/regenerate', [Admin\AiCopilotController::class, 'briefing'])->name('briefing.regenerate');
        });
        Route::get('gates/insights', Admin\AiOpsInsightsController::class)->middleware('throttle:60,1')->name('admin.gates.insights');

        // Events
        Route::middleware('can:manage events')->group(function () {
            Route::resource('categories', Admin\CategoryController::class, ['as' => 'admin']);
            Route::resource('events', Admin\EventController::class, ['as' => 'admin']);
            // Ticket types of an event (docs/ticket_types_contract.md §3) — JSON for the event form.
            Route::prefix('events/{event}/ticket-types')->name('admin.events.ticket-types.')->group(function () {
                Route::get('/', [Admin\EventTicketTypeController::class, 'index'])->name('index');
                Route::post('/', [Admin\EventTicketTypeController::class, 'store'])->name('store');
                Route::post('reorder', [Admin\EventTicketTypeController::class, 'reorder'])->name('reorder');
                Route::put('{ticketType}', [Admin\EventTicketTypeController::class, 'update'])->whereNumber('ticketType')->name('update');
                Route::delete('{ticketType}', [Admin\EventTicketTypeController::class, 'destroy'])->whereNumber('ticketType')->name('destroy');
            });
            Route::resource('event-tags', Admin\EventTagController::class, ['as' => 'admin']);
        });
        Route::resource('tickets', Admin\TicketController::class, ['as' => 'admin'])->only(['index', 'show', 'update'])->middleware('can:manage tickets');

        // Gates: scanner devices + pairing, live gate dashboard, scan log (contract §B3)
        Route::middleware('can:manage gates')->prefix('gates')->name('admin.gates.')->group(function () {
            Route::get('dashboard', [Admin\GateDashboardController::class, 'index'])->name('dashboard');
            Route::get('dashboard/data', [Admin\GateDashboardController::class, 'data'])->name('dashboard.data');
            Route::get('devices', [Admin\GateDeviceController::class, 'index'])->name('devices.index');
            Route::post('devices/pair', [Admin\GateDeviceController::class, 'pair'])->name('devices.pair');
            Route::put('devices/{device}', [Admin\GateDeviceController::class, 'update'])->name('devices.update');
            Route::post('devices/{device}/revoke', [Admin\GateDeviceController::class, 'revoke'])->name('devices.revoke');
            Route::get('scans', [Admin\GateScanController::class, 'index'])->name('scans.index');
            // Box office orders (docs/gate_sales_contract.md §7)
            Route::get('sales', [Admin\GateSalesController::class, 'index'])->name('sales.index');
            Route::get('sales/{order}', [Admin\GateSalesController::class, 'show'])->whereNumber('order')->name('sales.show');
            Route::post('sales/{order}/cancel', [Admin\GateSalesController::class, 'cancel'])->whereNumber('order')->name('sales.cancel');
            Route::post('sales/{order}/refund', [Admin\GateSalesController::class, 'refund'])->whereNumber('order')->name('sales.refund');
        });
        Route::resource('gates', Admin\GateController::class, ['as' => 'admin'])->except(['show'])->middleware('can:manage gates');

        // Payments
        Route::middleware('can:view payments')->group(function () {
            Route::get('payments', [Admin\PaymentController::class, 'index'])->name('admin.payments.index');
            Route::get('payments/{payment}', [Admin\PaymentController::class, 'show'])->name('admin.payments.show');
        });
        Route::post('payments/{payment}/refund', [Admin\PaymentController::class, 'refund'])
            ->middleware('can:refund payments')->name('admin.payments.refund');

        // Wallets & money
        Route::middleware('can:manage wallets')->group(function () {
            Route::get('wallets', [Admin\WalletController::class, 'index'])->name('admin.wallets.index');
            Route::get('wallets/user/{user}', [Admin\WalletController::class, 'forUser'])->name('admin.wallets.user');
            Route::post('wallets/user/{user}', [Admin\WalletController::class, 'createForUser'])->name('admin.wallets.create');
            Route::get('wallets/{wallet}', [Admin\WalletController::class, 'show'])->name('admin.wallets.show');
            Route::post('wallets/{wallet}/adjust', [Admin\WalletController::class, 'adjust'])->name('admin.wallets.adjust');
            Route::post('wallets/{wallet}/status', [Admin\WalletController::class, 'status'])->name('admin.wallets.status');
            Route::post('wallet-transactions/{transaction}/refund', [Admin\WalletController::class, 'refundCharge'])->name('admin.wallets.refund-charge');
            // Loyalty points
            Route::get('points', [Admin\PointsController::class, 'index'])->name('admin.points.index');
            Route::get('points/{account}', [Admin\PointsController::class, 'show'])->name('admin.points.show');
            Route::post('points/{account}/adjust', [Admin\PointsController::class, 'adjust'])->name('admin.points.adjust');
        });
        Route::middleware('can:manage refunds')->group(function () {
            Route::get('refund-requests', [Admin\RefundRequestController::class, 'index'])->name('admin.refund-requests.index');
            Route::get('refund-requests/{refundRequest}', [Admin\RefundRequestController::class, 'show'])->name('admin.refund-requests.show');
            Route::post('refund-requests/{refundRequest}/approve', [Admin\RefundRequestController::class, 'approve'])->name('admin.refund-requests.approve');
            Route::post('refund-requests/{refundRequest}/reject', [Admin\RefundRequestController::class, 'reject'])->name('admin.refund-requests.reject');
            Route::post('refund-requests/{refundRequest}/paid', [Admin\RefundRequestController::class, 'markPaid'])->name('admin.refund-requests.paid');
        });
        Route::middleware('can:manage deposits')->group(function () {
            Route::get('deposits', [Admin\DepositController::class, 'index'])->name('admin.deposits.index');
            Route::post('deposits/{deposit}/release', [Admin\DepositController::class, 'release'])->name('admin.deposits.release');
        });
        Route::middleware('can:manage vendors')->group(function () {
            Route::resource('vendors', Admin\VendorController::class, ['as' => 'admin']);
            Route::post('vendors/{vendor}/staff', [Admin\VendorController::class, 'attachStaff'])->name('admin.vendors.staff.attach');
            Route::delete('vendors/{vendor}/staff/{user}', [Admin\VendorController::class, 'detachStaff'])->name('admin.vendors.staff.detach');
        });

        // Listings & map
        Route::resource('restaurants', Admin\RestaurantController::class, ['as' => 'admin'])->middleware('can:manage restaurants');
        Route::resource('map-locations', Admin\MapLocationController::class, ['as' => 'admin'])->middleware('can:manage locations');

        // Places to visit + custom map (docs/places_content_contract.md §4)
        Route::middleware('can:manage places')->group(function () {
            Route::get('places/map', [Admin\PlaceController::class, 'map'])->name('admin.places.map');
            Route::post('places/reorder', [Admin\PlaceController::class, 'reorder'])->name('admin.places.reorder');
            Route::post('places/{place}/featured', [Admin\PlaceController::class, 'featured'])->name('admin.places.featured');
            Route::resource('places', Admin\PlaceController::class, ['as' => 'admin'])->except(['show']);
            Route::post('place-categories/reorder', [Admin\PlaceCategoryController::class, 'reorder'])->name('admin.place-categories.reorder');
            Route::resource('place-categories', Admin\PlaceCategoryController::class, ['as' => 'admin'])->except(['show']);
        });

        // Content collections + items, pages (contract §4)
        Route::middleware('can:manage content')->prefix('content')->group(function () {
            Route::post('collections/reorder', [Admin\ContentCollectionController::class, 'reorder'])->name('admin.content-collections.reorder');
            Route::resource('collections', Admin\ContentCollectionController::class)->except(['show'])
                ->names('admin.content-collections')->parameters(['collections' => 'collection']);
            Route::post('collections/{collection}/items/reorder', [Admin\ContentItemController::class, 'reorder'])->name('admin.content-items.reorder');
            Route::resource('collections.items', Admin\ContentItemController::class)->except(['show'])
                ->names('admin.content-items')->parameters(['collections' => 'collection', 'items' => 'item']);
            Route::post('pages/reorder', [Admin\ContentPageController::class, 'reorder'])->name('admin.content-pages.reorder');
            Route::resource('pages', Admin\ContentPageController::class)->except(['show'])
                ->names('admin.content-pages')->parameters(['pages' => 'page']);
        });

        // Communications
        Route::resource('announcements', Admin\AnnouncementController::class, ['as' => 'admin'])->middleware('can:manage announcements');
        Route::resource('notifications', Admin\NotificationController::class, ['as' => 'admin'])->middleware('can:manage notifications');

        // Users & roles (tenant-scoped role assignments)
        Route::resource('users', Admin\UserController::class, ['as' => 'admin'])->middleware('can:manage users');
        Route::resource('roles', Admin\RoleController::class, ['as' => 'admin'])->middleware('can:manage roles');

        // Settings, branding & legal of the current tenant
        Route::middleware('can:manage settings')->group(function () {
            Route::get('branding', [Admin\TenantController::class, 'editCurrent'])->name('admin.branding.edit');
            Route::put('branding', [Admin\TenantController::class, 'updateCurrent'])->name('admin.branding.update');
            Route::resource('app-settings', Admin\AppSettingController::class, ['as' => 'admin']);
            Route::resource('privacy-policies', Admin\PrivacyPolicyController::class, ['as' => 'admin']);
            Route::resource('terms-and-conditions', Admin\TermsAndConditionController::class, ['as' => 'admin']);
        });

        // Platform: tenants (super-admin only)
        Route::resource('tenants', Admin\TenantController::class, ['as' => 'admin'])
            ->except(['show', 'destroy'])->middleware('can:manage tenants');

        // Platform: demo requests from the landing page (super-admin only)
        Route::middleware('can:manage demo requests')->group(function () {
            Route::get('demo-requests', [Admin\DemoRequestController::class, 'index'])->name('admin.demo-requests.index');
            Route::get('demo-requests/{demoRequest}', [Admin\DemoRequestController::class, 'show'])->name('admin.demo-requests.show');
            Route::post('demo-requests/{demoRequest}/status', [Admin\DemoRequestController::class, 'status'])->name('admin.demo-requests.status');
        });
    });
});
