<?php

use App\Auth\Http\Controllers\AuthController;
use App\PropertyHistory\PropertyHistoryController;
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

    Route::get('/properties/{id}/history', [PropertyHistoryController::class, 'show'])
        ->whereUuid('id');
});
