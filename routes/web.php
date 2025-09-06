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
        
        // Add more admin routes here as needed
        // Events
        Route::prefix('events')->group(function () {
            // Add event routes
        });
        
        // Tickets
        Route::prefix('tickets')->group(function () {
            // Add ticket routes
        });
        
        // Users
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class, ['as' => 'admin']);
        
        // Roles
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class, ['as' => 'admin']);
        
        // Settings
        Route::prefix('settings')->group(function () {
            // Add settings routes
        });
    });
});
