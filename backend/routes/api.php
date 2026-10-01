<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminExportController;
use App\Http\Controllers\AdminGeofenceController;
use App\Http\Controllers\AdminPharmacyController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminTransactionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\GeofenceController;
use App\Http\Controllers\StockController;
use App\Http\Middleware\EnsureStaffOrAdmin;
use App\Services\Tile38Service;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (Tile38Service $tile38) {
    return response()->json([
        'status' => 'ok',
        'app' => 'PharmaLocate API',
        'tile38' => $tile38->status(),
    ]);
});

// Public auth endpoints.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public browsing: anyone (including guests) can view pharmacies and
// real-time medicine availability (FR6).
Route::get('/pharmacies', [PharmacyController::class, 'index']);
Route::get('/pharmacies/{pharmacy}', [PharmacyController::class, 'show']);
Route::get('/medicines', [MedicineController::class, 'index']);
Route::get('/medicines/{medicine}', [MedicineController::class, 'show']);
Route::get('/availability', [AvailabilityController::class, 'index']);
Route::get('/geofences', [GeofenceController::class, 'index']);

// Authenticated endpoints (require a Sanctum token).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Inquiries: customers submit and view their own; staff/admin manage all.
    Route::get('/inquiries', [InquiryController::class, 'index']);
    Route::post('/inquiries', [InquiryController::class, 'store']);
    Route::patch('/inquiries/{inquiry}', [InquiryController::class, 'respond']);

    Route::middleware(EnsureStaffOrAdmin::class)->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/stock', [StockController::class, 'index']);
        Route::patch('/stock/{pharmacy}/{medicine}', [StockController::class, 'update']);
        Route::get('/pharmacies', [AdminPharmacyController::class, 'index']);
        Route::post('/pharmacies', [AdminPharmacyController::class, 'store']);
        Route::patch('/pharmacies/{pharmacy}', [AdminPharmacyController::class, 'update']);
        Route::delete('/pharmacies/{pharmacy}', [AdminPharmacyController::class, 'destroy']);
        Route::get('/geofences', [AdminGeofenceController::class, 'index']);
        Route::post('/geofences', [AdminGeofenceController::class, 'store']);
        Route::patch('/geofences/{geofence}', [AdminGeofenceController::class, 'update']);
        Route::delete('/geofences/{geofence}', [AdminGeofenceController::class, 'destroy']);
        Route::post('/geofences/{geofence}/pharmacies', [AdminGeofenceController::class, 'attachPharmacy']);
        Route::delete('/geofences/{geofence}/pharmacies/{pharmacy}', [AdminGeofenceController::class, 'detachPharmacy']);

        Route::get('/pos/products', [AdminTransactionController::class, 'products']);
        Route::get('/transactions', [AdminTransactionController::class, 'index']);
        Route::post('/transactions', [AdminTransactionController::class, 'store']);

        Route::middleware('admin')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::patch('/users/{user}', [AdminUserController::class, 'update']);
            Route::get('/settings', [AdminSettingsController::class, 'index']);
            Route::patch('/settings', [AdminSettingsController::class, 'update']);
            Route::get('/export', [AdminExportController::class, 'export']);
        });
    });
});
