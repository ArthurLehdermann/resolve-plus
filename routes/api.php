<?php

use App\Auth\Http\Controllers\AuthController;
use App\Categories\Http\Controllers\AdminCategoryController;
use App\Categories\Http\Controllers\CategoryController;
use App\Payments\Http\PaymentController;
use App\PropertyHistory\Http\Controllers\PropertyController;
use App\PropertyHistory\Http\Controllers\PropertyTransferController;
use App\PropertyHistory\PropertyHistoryController;
use App\Proposals\Http\Controllers\ProposalController;
use App\Requests\Http\Controllers\RequestController;
use App\Services\Http\Controllers\MessageController;
use App\Services\Http\Controllers\ScheduleController;
use App\Services\Http\Controllers\ServiceController;
use App\Users\Http\Controllers\UserProfileController;
use App\Warranty\Http\Controllers\WarrantyController;
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

        Route::get('/properties', [PropertyController::class, 'index']);
        Route::post('/properties', [PropertyController::class, 'store']);
        Route::put('/properties/{id}', [PropertyController::class, 'update'])
            ->whereUuid('id');
        Route::post('/properties/{id}/transfer', [PropertyTransferController::class, 'initiate'])
            ->whereUuid('id');

        Route::get('/property-transfers', [PropertyTransferController::class, 'index']);
        Route::post('/property-transfers/{id}/accept', [PropertyTransferController::class, 'accept'])
            ->whereUuid('id');
        Route::post('/property-transfers/{id}/decline', [PropertyTransferController::class, 'decline'])
            ->whereUuid('id');

        Route::get('/requests', [RequestController::class, 'index']);
        Route::post('/requests', [RequestController::class, 'store']);
        Route::get('/requests/{solicitacao}', [RequestController::class, 'show'])
            ->whereUuid('solicitacao');
        Route::put('/requests/{solicitacao}', [RequestController::class, 'update'])
            ->whereUuid('solicitacao');
        Route::delete('/requests/{solicitacao}', [RequestController::class, 'destroy'])
            ->whereUuid('solicitacao');
        Route::post('/requests/{solicitacao}/photos', [RequestController::class, 'uploadPhoto'])
            ->middleware('throttle:upload')
            ->whereUuid('solicitacao');

        Route::get('/requests/{id}/proposals', [ProposalController::class, 'index'])
            ->whereUuid('id');
        Route::post('/requests/{id}/proposals', [ProposalController::class, 'store'])
            ->whereUuid('id');
        Route::post('/proposals/{id}/accept', [ProposalController::class, 'accept'])
            ->whereUuid('id');
        Route::post('/proposals/{id}/withdraw', [ProposalController::class, 'withdraw'])
            ->whereUuid('id');

        Route::get('/schedule', [ScheduleController::class, 'index']);
        Route::post('/schedule', [ScheduleController::class, 'store']);
        Route::put('/schedule/{id}', [ScheduleController::class, 'update'])
            ->whereUuid('id');

        Route::post('/services/{id}/start', [ServiceController::class, 'start'])
            ->whereUuid('id');
        Route::post('/services/{id}/finish', [ServiceController::class, 'finish'])
            ->whereUuid('id');
        Route::post('/services/{id}/approve', [ServiceController::class, 'approve'])
            ->whereUuid('id');
        Route::post('/services/{id}/contest', [ServiceController::class, 'contest'])
            ->whereUuid('id');
        Route::get('/services/{id}/messages', [MessageController::class, 'index'])
            ->whereUuid('id');
        Route::post('/services/{id}/messages', [MessageController::class, 'store'])
            ->whereUuid('id')
            ->middleware('throttle:chat');

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{id}', [PaymentController::class, 'show'])
            ->whereUuid('id');
        Route::get('/payments/{id}/events', [PaymentController::class, 'events'])
            ->whereUuid('id');
        Route::post('/payments/{id}/release', [PaymentController::class, 'release'])
            ->whereUuid('id');

        Route::get('/warranties', [WarrantyController::class, 'index']);
        Route::get('/warranties/{id}', [WarrantyController::class, 'show'])
            ->whereUuid('id');
        Route::post('/warranties/{id}/claim', [WarrantyController::class, 'claim'])
            ->whereUuid('id');
    });

    Route::get('/properties/{id}/history', [PropertyHistoryController::class, 'show'])
        ->whereUuid('id');

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{categoria}', [CategoryController::class, 'show']);

    Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::get('/categories/{categoria}', [AdminCategoryController::class, 'show']);
        Route::put('/categories/{categoria}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{categoria}', [AdminCategoryController::class, 'destroy']);
    });
});
