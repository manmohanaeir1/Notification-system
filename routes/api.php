<?php

use App\Http\Controllers\Api\AdminDashboardController;
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

        // Admin Dashboard Routes
        Route::prefix('admin')
            ->name('admin.')
            ->middleware('throttle:300,1') // Higher limit for admin operations
            ->group(function () {
                Route::get('stats', [AdminDashboardController::class, 'stats'])->name('stats');
                Route::get('user-stats', [AdminDashboardController::class, 'userStats'])->name('user-stats');
                Route::get('notifications/{notificationId}/lifecycle', [AdminDashboardController::class, 'notificationLifecycle'])->name('notification-lifecycle');
                Route::delete('notifications/bulk', [AdminDashboardController::class, 'bulkDelete'])->name('bulk-delete');
                Route::post('notifications/retry', [AdminDashboardController::class, 'bulkRetry'])->name('bulk-retry');
                Route::get('notifications/export', [AdminDashboardController::class, 'export'])->name('export');
                Route::get('health', [AdminDashboardController::class, 'health'])->name('health');
                Route::get('activity-log', [AdminDashboardController::class, 'activityLog'])->name('activity-log');
            });
    });

