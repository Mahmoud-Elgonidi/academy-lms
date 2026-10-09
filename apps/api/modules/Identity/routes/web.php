<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\ImpersonationController;
use Modules\Identity\Http\Controllers\SocialSignInController;
use Modules\Identity\Http\Controllers\WelcomeController;
use Modules\Identity\Http\Middleware\WatchImpersonation;

// Someone's own welcome link: choose a password once, then land signed in on their own area.
Route::middleware(['web', 'tenant', WatchImpersonation::class])->prefix('welcome')->group(function () {
    Route::get('{user}', [WelcomeController::class, 'show'])->middleware(['signed:relative', 'throttle:links'])->name('identity.welcome');
    Route::post('{user}', [WelcomeController::class, 'store'])->middleware(['signed:relative', 'throttle:10,1'])->name('identity.welcome.store');
});

// The platform team signed in as someone: the one-time link from the console, leaving, and where both end.
Route::middleware(['web', 'tenant', 'locale'])->prefix('impersonate')->group(function () {
    Route::get('ended', [ImpersonationController::class, 'ended'])->name('identity.impersonate.ended');
    Route::post('exit', [ImpersonationController::class, 'exit'])->name('identity.impersonate.exit');
    Route::get('{token}', [ImpersonationController::class, 'redeem'])->where('token', '[A-Za-z0-9]{40,64}')->middleware('throttle:20,1')->name('identity.impersonate');
});

// Signing in with Google or Apple on the panels: leave from the academy's host, come back to the providers' one
// callback on the platform (Apple posts from its own site: no session, no CSRF token there), and finish on the
// academy's host, where the browser that started holds the secret its ticket needs.
Route::middleware(['web', 'tenant', 'locale', WatchImpersonation::class])->prefix('auth/social')->group(function () {
    Route::get('finish', [SocialSignInController::class, 'finish'])->middleware('throttle:20,1')->name('identity.social.finish');
    Route::get('{provider}/start', [SocialSignInController::class, 'start'])->whereIn('provider', ['google', 'apple'])->middleware('throttle:20,1')->name('identity.social.start');
});
Route::match(['get', 'post'], 'auth/social/{provider}/callback', [SocialSignInController::class, 'callback'])
    ->whereIn('provider', ['google', 'apple'])->middleware('throttle:30,1')->name('identity.social.callback');
