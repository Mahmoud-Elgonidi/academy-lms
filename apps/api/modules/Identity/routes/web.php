<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\WelcomeController;

// Someone's own welcome link: choose a password once, then land signed in on their own area.
Route::middleware(['web', 'tenant'])->prefix('welcome')->group(function () {
    Route::get('{user}', [WelcomeController::class, 'show'])->middleware('signed:relative')->name('identity.welcome');
    Route::post('{user}', [WelcomeController::class, 'store'])->middleware(['signed:relative', 'throttle:10,1'])->name('identity.welcome.store');
});
