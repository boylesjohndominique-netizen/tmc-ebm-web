<?php

use App\Http\Controllers\Admin\BorrowingManagementController;
use App\Http\Controllers\Admin\BorrowingPickupController;
use App\Http\Controllers\Admin\EquipmentManagementController;
use App\Http\Controllers\Admin\NotificationManagementController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReturnHandoverController;
use App\Http\Controllers\Admin\ReturnManagementController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AppealController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Login
Route::post('/login', [
    AuthController::class,
    'login',
]);

// Public student self-registration (mobile app) — creates a Pending account
Route::post('/register', [
    AuthController::class,
    'register',
]);

Route::post('/register/send-code', [
    AuthController::class,
    'sendRegistrationCode',
]);

Route::post('/register/verify-code', [
    AuthController::class,
    'verifyRegistrationCode',
]);

// =====================================================
// EMAIL VERIFICATION ROUTES (PUBLIC)
// =====================================================

Route::post('/verify-email', [
    AuthController::class,
    'verifyEmail',
]);

Route::post('/resend-verification', [
    AuthController::class,
    'resendVerification',
]);

// =====================================================
// MOBILE APP LOGIN
// =====================================================

Route::post('/mobile/login', [
    AuthController::class,
    'mobileLogin',
]);

// =====================================================
// BAN APPEAL (PUBLIC — banned users have no valid token)
// =====================================================

Route::post('/appeals', [
    AppealController::class,
    'store',
])->middleware('throttle:5,1');

// =====================================================
// FORGOT PASSWORD ROUTES (CODE-BASED FOR MOBILE)
// =====================================================

// Request password reset code
Route::post('/forgot-password', [
    AuthController::class,
    'forgotPassword',
]);

// Public equipment image streaming (works across localhost, LAN, and ngrok)
Route::get('/equipment-media/{filename}', [
    EquipmentController::class,
    'media',
])->where('filename', '[A-Za-z0-9._-]+');

Route::get('/user-avatar/{filename}', [
    MobileController::class,
    'userAvatar',
])->where('filename', '[A-Za-z0-9._-]+');

// Reset password using code
Route::post('/reset-password', [
    AuthController::class,
    'resetPassword',
]);

// =====================================================
// PROTECTED ROUTES
// =====================================================

Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::post('/logout', [
        AuthController::class,
        'logout',
    ]);

    Route::get('/me', [
        AuthController::class,
        'me',
    ]);

    // =================================================
    // DASHBOARD (ADMIN OVERVIEW)
    // =================================================

    Route::get('/dashboard', [
        DashboardController::class,
        'index',
    ])->middleware('perm:dashboard.view');

    Route::get('/admin/analytics', [
        DashboardController::class,
        'analytics',
    ])->middleware('perm:dashboard.view');

    // =================================================
    // EQUIPMENT (READ — USED BY ADMIN PAGES)
    // =================================================

    Route::get('/equipment', [
        EquipmentController::class,
        'index',
    ])->middleware('perm:equipment.view');

    // =================================================
    // NOTIFICATIONS (own notifications — all staff users)
    // =================================================

    Route::get('/notifications', [
        NotificationController::class,
        'index',
    ]);

    Route::get('/notifications/unread-count', [
        NotificationController::class,
        'unreadCount',
    ]);

    Route::put('/notifications/read-all', [
        NotificationController::class,
        'readAll',
    ]);

    Route::post('/notifications/bulk', [
        NotificationController::class,
        'bulk',
    ]);

    Route::post('/notifications/archive-read', [
        NotificationController::class,
        'archiveRead',
    ]);

    Route::delete('/notifications/archived', [
        NotificationController::class,
        'clearArchived',
    ]);

    Route::put('/notifications/{notification}/read', [
        NotificationController::class,
        'read',
    ]);

    // =================================================
    // PROFILE
    // =================================================

    Route::get('/profile', [
        ProfileController::class,
        'show',
    ]);

    Route::put('/profile', [
        ProfileController::class,
        'update',
    ]);

    Route::put('/profile/password', [
        ProfileController::class,
        'password',
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
                'index',
            ])->middleware('perm:users.view');

            Route::post('/users', [
                UserManagementController::class,
                'store',
            ])->middleware('perm:users.create');

            Route::get('/users/{user}', [
                UserManagementController::class,
                'show',
            ])->middleware('perm:users.view');

            Route::put('/users/{user}', [
                UserManagementController::class,
                'update',
            ])->middleware('perm:users.edit');

            Route::delete('/users/{user}', [
                UserManagementController::class,
                'destroy',
            ])->middleware('perm:users.delete');

            // Account verification (student self-registrations)
            Route::put('/users/{user}/verify', [
                UserManagementController::class,
                'verify',
            ])->middleware('perm:users.verify');

            // Ban / unban any non-admin account
            Route::put('/users/{user}/ban', [
                UserManagementController::class,
                'ban',
            ])->middleware('perm:users.ban');

            Route::put('/users/{user}/unban', [
                UserManagementController::class,
                'unban',
            ])->middleware('perm:users.ban');

            // View a student's submitted ID photos (private disk, streamed to
            // authorized staff only — images are never publicly reachable).
            Route::get('/users/{user}/id-photo/{side}', [
                UserManagementController::class,
                'idPhoto',
            ])->middleware('perm:users.view');

            // Equipment
            Route::get('/equipment/{equipment}', [
                EquipmentManagementController::class,
                'show',
            ])->middleware('perm:equipment.view');

            Route::post('/equipment', [
                EquipmentManagementController::class,
                'store',
            ])->middleware('perm:equipment.create');

            Route::put('/equipment/{equipment}', [
                EquipmentManagementController::class,
                'update',
            ])->middleware('perm:equipment.edit');

            Route::post('/equipment/{equipment}', [
                EquipmentManagementController::class,
                'update',
            ])->middleware('perm:equipment.edit');

            Route::delete('/equipment/{equipment}', [
                EquipmentManagementController::class,
                'destroy',
            ])->middleware('perm:equipment.delete');

            // Borrowing Requests
            Route::get('/borrowings/receipt', [BorrowingPickupController::class, 'show'])
                ->middleware(['perm:borrowings.view', 'perm:borrowings.handover', 'throttle:30,1']);
            Route::post('/borrowings/pickup', [BorrowingPickupController::class, 'process'])
                ->middleware(['perm:borrowings.view', 'perm:borrowings.handover', 'throttle:30,1']);

            Route::get('/borrowings', [
                BorrowingManagementController::class,
                'index',
            ])->middleware('perm:borrowings.view');

            Route::put('/borrowings/{borrowing}/process', [
                BorrowingManagementController::class,
                'process',
            ])->middleware('perm:borrowings.process');

            // Return Requests
            Route::get('/returns/receipt', [ReturnHandoverController::class, 'show'])
                ->middleware(['perm:returns.view', 'perm:returns.handover', 'throttle:30,1']);
            Route::post('/returns/handover', [ReturnHandoverController::class, 'process'])
                ->middleware(['perm:returns.view', 'perm:returns.handover', 'throttle:30,1']);

            Route::get('/returns', [
                ReturnManagementController::class,
                'index',
            ])->middleware('perm:returns.view');

            Route::put('/returns/{returnRequest}/process', [
                ReturnManagementController::class,
                'process',
            ])->middleware('perm:returns.process');

            // Admin Notifications (broadcast tool)
            Route::post('/notifications', [
                NotificationManagementController::class,
                'store',
            ])->middleware('perm:notifications.send');

            Route::get('/notification-users', [
                NotificationManagementController::class,
                'users',
            ])->middleware('perm:notifications.send');

            // Reports
            Route::get('/reports', [
                ReportController::class,
                'index',
            ])->middleware('perm:reports.view');

            Route::get('/reports/export', [
                ReportController::class,
                'export',
            ])->middleware('perm:reports.view');

            // System Settings (overdue fee, etc.)
            Route::get('/settings', [
                SettingController::class,
                'index',
            ])->middleware('perm:settings.view');

            Route::put('/settings', [
                SettingController::class,
                'update',
            ])->middleware('perm:settings.update');

            Route::put('/settings/policies', [
                SettingController::class,
                'updatePolicies',
            ])->middleware('perm:settings.update');

            // Transaction History (processed requests audit)
            Route::get('/transactions', [
                TransactionController::class,
                'index',
            ])->middleware('perm:transactions.view');

            // =============================================
            // PERMISSION MANAGEMENT (SUPER ADMIN ONLY)
            // =============================================

            Route::middleware('super.admin')->group(function () {

                // Catalog + editable users
                Route::get('/permissions', [
                    PermissionController::class,
                    'index',
                ]);

                Route::get('/permissions/{user}', [
                    PermissionController::class,
                    'show',
                ]);

                Route::put('/permissions/{user}', [
                    PermissionController::class,
                    'update',
                ]);

                Route::put('/permissions/{user}/reset', [
                    PermissionController::class,
                    'reset',
                ]);

            });

        });

});

// =====================================================
// MOBILE APP ROUTES (authenticated)
// =====================================================

Route::middleware('auth:sanctum')
    ->prefix('mobile')
    ->group(function () {

        // Equipment
        Route::get('/equipment', [MobileController::class, 'equipment']);
        Route::get('/equipment/{equipment}', [MobileController::class, 'equipmentShow']);

        // Borrowing
        Route::post('/borrow', [MobileController::class, 'submitBorrowRequest']);
        Route::get('/borrowings', [MobileController::class, 'myBorrowings']);
        Route::put('/borrowings/{borrowing}/cancel', [MobileController::class, 'cancelBorrowing']);

        // Returns
        Route::post('/borrowings/{borrowing}/return', [MobileController::class, 'submitReturn']);

        // Notifications
        Route::get('/notifications', [MobileController::class, 'notifications']);
        Route::get('/notifications/unread-count', [MobileController::class, 'unreadCount']);
        Route::put('/notifications/{notification}/read', [MobileController::class, 'markRead']);
        Route::put('/notifications/read-all', [MobileController::class, 'markAllRead']);

        // Profile
        Route::get('/profile', [MobileController::class, 'profile']);
        Route::put('/profile', [MobileController::class, 'updateProfile']);
        Route::post('/profile', [MobileController::class, 'updateProfile']);
        Route::post('/profile/avatar', [MobileController::class, 'updateAvatar']);
        Route::put('/profile/password', [MobileController::class, 'changePassword']);

        // ID verification (private storage; admin review via the Users page)
        Route::post('/id-verification', [MobileController::class, 'submitIdVerification']);
        Route::get('/id-photo/{side}', [MobileController::class, 'myIdPhoto'])->where('side', 'front|back');

        // System settings & platform policies (read-only for mobile users)
        Route::get('/settings', [MobileController::class, 'settings']);

        // Logout
        Route::post('/logout', [AuthController::class, 'logout']);
    });
