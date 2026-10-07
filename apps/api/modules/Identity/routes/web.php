<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\ImpersonationController;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Identity\Http\Middleware\WatchImpersonation;

// Someone's own welcome link: choose a password once, then land signed in on their own area.
Route::middleware(['web', 'tenant', WatchImpersonation::class])->prefix('welcome')->group(function () {
    Route::get('{user}', [WelcomeController::class, 'show'])->middleware('signed:relative')->name('identity.welcome');
    Route::post('{user}', [WelcomeController::class, 'store'])->middleware(['signed:relative', 'throttle:10,1'])->name('identity.welcome.store');
});

// The platform team signed in as someone: the one-time link from the console, leaving, and where both end.
Route::middleware(['web', 'tenant', 'locale'])->prefix('impersonate')->group(function () {
    Route::get('ended', [ImpersonationController::class, 'ended'])->name('identity.impersonate.ended');
    Route::post('exit', [ImpersonationController::class, 'exit'])->name('identity.impersonate.exit');
    Route::get('{token}', [ImpersonationController::class, 'redeem'])->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:20,1')->name('identity.impersonate');
});
