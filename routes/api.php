<?php

use App\Auth\Http\Controllers\AuthController;
use App\Users\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:auth-register');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:auth-login');

        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:auth-login');

        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:auth-login');

        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware('auth:sanctum');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/users/me', [UserProfileController::class, 'show']);
        Route::put('/users/me', [UserProfileController::class, 'update']);
        Route::post('/users/photo', [UserProfileController::class, 'uploadPhoto'])
            ->middleware('throttle:upload');
    });
});
