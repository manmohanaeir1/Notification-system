<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationMonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware('throttle:60,1')
    ->group(function () {
        Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store');
        Route::get('notifications', [NotificationMonitorController::class, 'index'])->name('notifications.index');
        Route::get('notifications/summary', [NotificationMonitorController::class, 'summary'])->name('notifications.summary');
    });
