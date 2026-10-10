<?php

use Illuminate\Support\Facades\Route;
use Modules\Privacy\Http\V1\Controllers\DataRequestsController;
use Modules\Privacy\Http\V1\Controllers\DetailsController;

// A person's own data, from the apps and the portal (the same as "My data" in /my and /teach): details put right on
// the spot, a copy, a correction the academy makes, erasure, and taking a correction or an erasure back while it waits.
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('me/data-requests')->group(function () {
        Route::get('/', [DataRequestsController::class, 'index'])->name('privacy.requests');
        Route::post('/', [DataRequestsController::class, 'store'])->middleware(['idempotent', 'throttle:10,1'])->name('privacy.requests.store');
        Route::post('{id}/cancel', [DataRequestsController::class, 'cancel'])->middleware('throttle:20,1')->name('privacy.requests.cancel');
    });
    Route::patch('me/details/{subject}', [DetailsController::class, 'update'])->middleware('throttle:20,1')->name('privacy.details.update');
});
