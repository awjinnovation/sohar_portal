<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;

// Redirect root to admin dashboard
Route::get('/', fn() => redirect('/admin/dashboard'));

// Add fallback login route for Laravel's default auth middleware
Route::get('/login', fn() => redirect('/admin/login'))->name('login');

// Admin Routes
Route::prefix('admin')->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login.form');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login');
    });
    
    // Authenticated admin routes
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

        // Media Management
        Route::post('/media/upload', [\App\Http\Controllers\Admin\MediaController::class, 'upload'])->name('admin.media.upload');
        Route::delete('/media/delete', [\App\Http\Controllers\Admin\MediaController::class, 'delete'])->name('admin.media.delete');

        // Festival Management
        Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class, ['as' => 'admin']);
        Route::resource('events', \App\Http\Controllers\Admin\EventController::class, ['as' => 'admin']);
        Route::resource('event-tags', \App\Http\Controllers\Admin\EventTagController::class, ['as' => 'admin']);
        Route::resource('tickets', \App\Http\Controllers\Admin\TicketController::class, ['as' => 'admin']);


        // Restaurant Management
        Route::resource('restaurants', \App\Http\Controllers\Admin\RestaurantController::class, ['as' => 'admin']);

        // Locations
        Route::resource('locations', \App\Http\Controllers\Admin\LocationController::class, ['as' => 'admin']);



        // Communications
        Route::resource('announcements', \App\Http\Controllers\Admin\AnnouncementController::class, ['as' => 'admin']);
        if (file_exists(app_path('Http/Controllers/Admin/NotificationController.php'))) {
            Route::resource('notifications', \App\Http\Controllers\Admin\NotificationController::class, ['as' => 'admin']);
        }

        // User Management
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class, ['as' => 'admin']);
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class, ['as' => 'admin']);
        if (file_exists(app_path('Http/Controllers/Admin/UserFavoriteController.php'))) {
            Route::resource('user-favorites', \App\Http\Controllers\Admin\UserFavoriteController::class, ['as' => 'admin']);
        }
        if (file_exists(app_path('Http/Controllers/Admin/UserInterestController.php'))) {
            Route::resource('user-interests', \App\Http\Controllers\Admin\UserInterestController::class, ['as' => 'admin']);
        }
        if (file_exists(app_path('Http/Controllers/Admin/OtpVerificationController.php'))) {
            Route::resource('otp-verifications', \App\Http\Controllers\Admin\OtpVerificationController::class, ['as' => 'admin']);
        }
        if (file_exists(app_path('Http/Controllers/Admin/PaymentController.php'))) {
            Route::resource('payments', \App\Http\Controllers\Admin\PaymentController::class, ['as' => 'admin']);
        }

        // App Settings
        if (file_exists(app_path('Http/Controllers/Admin/AppSettingController.php'))) {
            Route::resource('app-settings', \App\Http\Controllers\Admin\AppSettingController::class, ['as' => 'admin']);
        }

        // Legal
        Route::resource('privacy-policies', \App\Http\Controllers\Admin\PrivacyPolicyController::class, ['as' => 'admin']);
        Route::resource('terms-and-conditions', \App\Http\Controllers\Admin\TermsAndConditionController::class, ['as' => 'admin']);
    });
});
