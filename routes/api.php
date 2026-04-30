<?php

use Illuminate\Support\Facades\Route;
use Src\Domain\Auth\Http\Controllers\AuthController;

Route::prefix('v1/auth')->group(function () {
    // Public routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
