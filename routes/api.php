<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/{id}/notifications', [AlertController::class, 'notifications']);
    Route::get('/notifications/recent', [NotificationController::class, 'recent']);

    Route::post('/fcm-token/register', [FcmTokenController::class, 'register']);
    Route::delete('/fcm-token/unregister', [FcmTokenController::class, 'unregister']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
