<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CarModelController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FillUpController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserRoleController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
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

Route::middleware('auth:sanctum')->group(function ()
{
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
        Route::delete('fill-ups/{fillUp}', [FillUpController::class, 'destroy']);

        // Trips
        Route::get('trips', [TripController::class, 'index']);
        Route::post('trips', [TripController::class, 'store']);
    });
});
