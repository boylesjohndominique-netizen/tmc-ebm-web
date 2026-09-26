<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\EquipmentManagementController;
use App\Http\Controllers\Admin\BorrowingManagementController;
use App\Http\Controllers\Admin\ReturnManagementController;
use App\Http\Controllers\Admin\NotificationManagementController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController;


/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Login
Route::post('/login', [
    AuthController::class,
    'login'
]);

// Public student self-registration (mobile app) — creates a Pending account
Route::post('/register', [
    AuthController::class,
    'register'
]);


// =====================================================
// FORGOT PASSWORD ROUTES
// =====================================================

// Request password reset link
Route::post('/forgot-password', [
    AuthController::class,
    'forgotPassword'
]);

// Reset password using token
Route::post('/reset-password', [
    AuthController::class,
    'resetPassword'
]);


// =====================================================
// PROTECTED ROUTES
// =====================================================

Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::post('/logout', [
        AuthController::class,
        'logout'
    ]);

    Route::get('/me', [
        AuthController::class,
        'me'
    ]);


    // =================================================
    // DASHBOARD (ADMIN OVERVIEW)
    // =================================================

    Route::get('/dashboard', [
        DashboardController::class,
        'index'
    ])->middleware('perm:dashboard.view');

    Route::get('/admin/analytics', [
        DashboardController::class,
        'analytics'
    ])->middleware('perm:dashboard.view');


    // =================================================
    // EQUIPMENT (READ — USED BY ADMIN PAGES)
    // =================================================

    Route::get('/equipment', [
        EquipmentController::class,
        'index'
    ])->middleware('perm:equipment.view');


    // =================================================
    // NOTIFICATIONS (own notifications — all staff users)
    // =================================================

    Route::get('/notifications', [
        NotificationController::class,
        'index'
    ]);

    Route::get('/notifications/unread-count', [
        NotificationController::class,
        'unreadCount'
    ]);

    Route::put('/notifications/read-all', [
        NotificationController::class,
        'readAll'
    ]);

    Route::put('/notifications/{notification}/read', [
        NotificationController::class,
        'read'
    ]);


    // =================================================
    // PROFILE
    // =================================================

    Route::get('/profile', [
        ProfileController::class,
        'show'
    ]);

    Route::put('/profile', [
        ProfileController::class,
        'update'
    ]);

    Route::put('/profile/password', [
        ProfileController::class,
        'password'
    ]);


    // =================================================
    // ADMIN ROUTES
    // =================================================

    Route::middleware('admin')
        ->prefix('admin')
        ->group(function () {

            // Users
            Route::get('/users', [
                UserManagementController::class,
                'index'
            ])->middleware('perm:users.view');

            Route::post('/users', [
                UserManagementController::class,
                'store'
            ])->middleware('perm:users.create');

            Route::get('/users/{user}', [
                UserManagementController::class,
                'show'
            ])->middleware('perm:users.view');

            Route::put('/users/{user}', [
                UserManagementController::class,
                'update'
            ])->middleware('perm:users.edit');

            Route::delete('/users/{user}', [
                UserManagementController::class,
                'destroy'
            ])->middleware('perm:users.delete');

            // Account verification (student self-registrations)
            Route::put('/users/{user}/verify', [
                UserManagementController::class,
                'verify'
            ])->middleware('perm:users.verify');

            // Ban / unban any non-admin account
            Route::put('/users/{user}/ban', [
                UserManagementController::class,
                'ban'
            ])->middleware('perm:users.ban');

            Route::put('/users/{user}/unban', [
                UserManagementController::class,
                'unban'
            ])->middleware('perm:users.ban');


            // Equipment
            Route::get('/equipment/{equipment}', [
                EquipmentManagementController::class,
                'show'
            ])->middleware('perm:equipment.view');

            Route::post('/equipment', [
                EquipmentManagementController::class,
                'store'
            ])->middleware('perm:equipment.create');

            Route::put('/equipment/{equipment}', [
                EquipmentManagementController::class,
                'update'
            ])->middleware('perm:equipment.edit');

            Route::delete('/equipment/{equipment}', [
                EquipmentManagementController::class,
                'destroy'
            ])->middleware('perm:equipment.delete');


            // Borrowing Requests
            Route::get('/borrowings', [
                BorrowingManagementController::class,
                'index'
            ])->middleware('perm:borrowings.view');

            Route::put('/borrowings/{borrowing}/process', [
                BorrowingManagementController::class,
                'process'
            ])->middleware('perm:borrowings.process');


            // Return Requests
            Route::get('/returns', [
                ReturnManagementController::class,
                'index'
            ])->middleware('perm:returns.view');

            Route::put('/returns/{returnRequest}/process', [
                ReturnManagementController::class,
                'process'
            ])->middleware('perm:returns.process');


            // Admin Notifications (broadcast tool)
            Route::post('/notifications', [
                NotificationManagementController::class,
                'store'
            ])->middleware('perm:notifications.send');

            Route::get('/notification-users', [
                NotificationManagementController::class,
                'users'
            ])->middleware('perm:notifications.send');


            // Reports
            Route::get('/reports', [
                ReportController::class,
                'index'
            ])->middleware('perm:reports.view');


            // Transaction History (processed requests audit)
            Route::get('/transactions', [
                TransactionController::class,
                'index'
            ])->middleware('perm:transactions.view');


            // =============================================
            // PERMISSION MANAGEMENT (SUPER ADMIN ONLY)
            // =============================================

            Route::middleware('super.admin')->group(function () {

                // Catalog + editable users
                Route::get('/permissions', [
                    PermissionController::class,
                    'index'
                ]);

                Route::get('/permissions/{user}', [
                    PermissionController::class,
                    'show'
                ]);

                Route::put('/permissions/{user}', [
                    PermissionController::class,
                    'update'
                ]);

                Route::put('/permissions/{user}/reset', [
                    PermissionController::class,
                    'reset'
                ]);

            });

        });

});
