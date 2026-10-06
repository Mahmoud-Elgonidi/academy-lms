<?php

use Illuminate\Support\Facades\Route;
use Modules\Communication\Http\V1\Controllers\NotificationsController;

// The signed-in person's inbox, notification choices and push devices.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationsController::class, 'read'])->name('notifications.read');
    Route::get('notifications/preferences', [NotificationsController::class, 'preferences'])->name('notifications.preferences');
    Route::put('notifications/preferences', [NotificationsController::class, 'savePreferences'])->name('notifications.preferences.save');
    Route::post('devices', [NotificationsController::class, 'registerDevice'])->name('devices.register');
    Route::delete('devices', [NotificationsController::class, 'forgetDevice'])->name('devices.forget');
});
