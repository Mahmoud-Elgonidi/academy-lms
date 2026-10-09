<?php

use Illuminate\Support\Facades\Route;
use Modules\Privacy\Http\V1\Controllers\DataRequestsController;

// A person's own data, from the apps and the portal (the same as "My data" in /my and /teach): a copy, erasure, and
// taking an erasure back while it waits.
Route::middleware('auth:sanctum')->prefix('me/data-requests')->group(function () {
    Route::get('/', [DataRequestsController::class, 'index'])->name('privacy.requests');
    Route::post('/', [DataRequestsController::class, 'store'])->middleware(['idempotent', 'throttle:10,1'])->name('privacy.requests.store');
    Route::post('{id}/cancel', [DataRequestsController::class, 'cancel'])->middleware('throttle:20,1')->name('privacy.requests.cancel');
});
