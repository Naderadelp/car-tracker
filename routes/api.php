<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CarLogController;
use App\Http\Controllers\CarModelController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FillUpController;
use App\Http\Controllers\ParkingRecordController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ServiceCenterController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\GasStationCheckInController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UpcomingServiceController;
use App\Http\Controllers\FuelPriceController;
use App\Http\Controllers\UserRoleController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Public lookup routes (used during registration)
Route::get('brands', [BrandController::class, 'index']);
Route::get('brands/{brand}/car-models', [CarModelController::class, 'index']);
Route::get('brands/{brand}/car-model-names', [CarModelController::class, 'names']);
Route::get('brands/{brand}/car-model-years', [CarModelController::class, 'years']);

Route::middleware(['auth:sanctum', \App\Http\Middleware\FirebaseTokenStoreMiddleware::class])->group(function ()
{
    // Home dashboard
    Route::get('home', HomeController::class);

    // Roles
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    Route::delete('roles/{role}/permissions/{permission}', [RoleController::class, 'revokePermission']);

    // Permissions
    Route::get('permissions', [PermissionController::class, 'index']);

    // User → Roles
    Route::get('users/{user}/roles', [UserRoleController::class, 'index']);
    Route::post('users/{user}/roles', [UserRoleController::class, 'store']);
    Route::delete('users/{user}/roles/{role}', [UserRoleController::class, 'destroy']);

    // Brands & Car Models (admin management)
    Route::post('brands', [BrandController::class, 'store']);
    Route::get('brands/{brand}', [BrandController::class, 'show']);
    Route::put('brands/{brand}', [BrandController::class, 'update']);
    Route::delete('brands/{brand}', [BrandController::class, 'destroy']);

    Route::post('brands/{brand}/car-models', [CarModelController::class, 'store']);
    Route::put('brands/{brand}/car-models/{carModel}', [CarModelController::class, 'update']);
    Route::delete('brands/{brand}/car-models/{carModel}', [CarModelController::class, 'destroy']);

    // Items (master parts inventory)
    Route::get   ('items',        [ItemController::class, 'index']);
    Route::post  ('items',        [ItemController::class, 'store']);
    Route::get   ('items/{item}', [ItemController::class, 'show']);
    Route::match (['put', 'patch'], 'items/{item}', [ItemController::class, 'update']);
    Route::delete('items/{item}', [ItemController::class, 'destroy']);

    // Service centers (per brand) — full CRUD
    Route::get   ('brands/{brand}/service-centers',                 [ServiceCenterController::class, 'index']);
    Route::post  ('brands/{brand}/service-centers',                 [ServiceCenterController::class, 'store']);
    Route::get   ('brands/{brand}/service-centers/{serviceCenter}', [ServiceCenterController::class, 'show']);
    Route::match (['put', 'patch'], 'brands/{brand}/service-centers/{serviceCenter}', [ServiceCenterController::class, 'update']);
    Route::delete('brands/{brand}/service-centers/{serviceCenter}', [ServiceCenterController::class, 'destroy']);

    // Catalogue services (admin) — full CRUD
    Route::get   ('services',                       [ServiceController::class, 'index']);
    Route::post  ('services',                       [ServiceController::class, 'storeCatalogue']);
    Route::get   ('services/{service}',             [ServiceController::class, 'show']);
    Route::match (['put', 'patch'], 'services/{service}', [ServiceController::class, 'update']);
    Route::delete('services/{service}',             [ServiceController::class, 'destroy']);

    // User-service create (per car)
    Route::post('cars/{car}/services', [ServiceController::class, 'store']);

    Route::prefix('cars/{car}')->group(function () {
        // Documents
        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store']);
        Route::put('documents/{document}', [DocumentController::class, 'update']);
        Route::get('documents/{document}/file', [DocumentController::class, 'show']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

        // Fill-Ups
        Route::get('fill-ups', [FillUpController::class, 'index']);
        Route::post('fill-ups', [FillUpController::class, 'store']);
        Route::post('fill-ups/quick', [FillUpController::class, 'quick']);
        Route::delete('fill-ups/{fillUp}', [FillUpController::class, 'destroy']);

        // Car Logs
        Route::get('logs', [CarLogController::class, 'index']);
        Route::post('logs', [CarLogController::class, 'store']);
        Route::get('logs/{log}', [CarLogController::class, 'show']);
        Route::put('logs/{log}', [CarLogController::class, 'update']);
        Route::delete('logs/{log}', [CarLogController::class, 'destroy']);

        // Reminders
        Route::get('reminders', [ReminderController::class, 'index']);
        Route::post('reminders', [ReminderController::class, 'store']);
        Route::get('reminders/{reminder}', [ReminderController::class, 'show']);
        Route::put('reminders/{reminder}', [ReminderController::class, 'update']);
        Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy']);

        // Trips
        Route::get('trips', [TripController::class, 'index']);
        Route::post('trips', [TripController::class, 'store']);

        // Upcoming maintenance
        Route::get('upcoming-services', [UpcomingServiceController::class, 'index']);

        // Parking Records
        Route::get   ('parking-records/current',         [ParkingRecordController::class, 'current']);
        Route::get   ('parking-records',                 [ParkingRecordController::class, 'index']);
        Route::post  ('parking-records',                 [ParkingRecordController::class, 'store']);
        Route::delete('parking-records/{parkingRecord}', [ParkingRecordController::class, 'destroy']);

        // Gas Station Check-In
        Route::post('gas-station-check-in', GasStationCheckInController::class);
    });

    // Fuel Prices
    Route::get('fuel-prices', [FuelPriceController::class, 'index']);
    Route::post('fuel-prices', [FuelPriceController::class, 'store']);
    Route::put('fuel-prices/{fuelPrice}', [FuelPriceController::class, 'update']);
    Route::patch('fuel-prices/{fuelPrice}', [FuelPriceController::class, 'update']);
});
