<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;

// Redirect root to admin
Route::get('/', fn() => redirect('/admin/login'));

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
        
        // Festival Management
        Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class, ['as' => 'admin']);
        Route::resource('events', \App\Http\Controllers\Admin\EventController::class, ['as' => 'admin']);
        Route::resource('restaurants', \App\Http\Controllers\Admin\RestaurantController::class, ['as' => 'admin']);
        
        // Heritage & Culture
        Route::resource('heritage-villages', \App\Http\Controllers\Admin\HeritageVillageController::class, ['as' => 'admin']);
        
        // Communications
        Route::resource('announcements', \App\Http\Controllers\Admin\AnnouncementController::class, ['as' => 'admin']);
        Route::resource('emergency-contacts', \App\Http\Controllers\Admin\EmergencyContactController::class, ['as' => 'admin']);
        
        // User Management
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class, ['as' => 'admin']);
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class, ['as' => 'admin']);
    });
});
