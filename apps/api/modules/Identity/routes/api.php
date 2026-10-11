<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\V1\Controllers\AccountsController;
use Modules\Identity\Http\V1\Controllers\AuthController;
use Modules\Identity\Http\V1\Controllers\CodeSignInController;
use Modules\Identity\Http\V1\Controllers\ContactController;
use Modules\Identity\Http\V1\Controllers\RealtimeController;
use Modules\Identity\Http\V1\Controllers\SessionsController;
use Modules\Identity\Http\V1\Controllers\SocialSignInController;

// Mounted at /api/v1 with the api, tenant and locale middleware (IdentityServiceProvider).

// Five tries a minute for each login and address (LoginRequest); and a ceiling for the address, so one place cannot try many accounts.
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:30,1')->name('identity.login');
Route::post('auth/code', [CodeSignInController::class, 'send'])->name('identity.code.send');
Route::post('auth/code/verify', [CodeSignInController::class, 'verify'])->name('identity.code.verify');
Route::post('accounts/sign-in', [AccountsController::class, 'signIn'])->name('identity.accounts.sign-in');
Route::post('auth/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:10,1')->name('identity.two-factor');

// Google and Apple: the phone's own sign-in (an ID token), or the portal through the browser (start, then the ticket).
Route::get('auth/social', [SocialSignInController::class, 'providers'])->name('identity.social.providers');
Route::middleware('throttle:20,1')->group(function (): void {
    Route::post('auth/social', [SocialSignInController::class, 'token'])->name('identity.social.token');
    Route::post('auth/social/ticket', [SocialSignInController::class, 'ticket'])->name('identity.social.ticket');
    Route::post('auth/social/{provider}/start', [SocialSignInController::class, 'start'])->whereIn('provider', ['google', 'apple'])->name('identity.social.api-start');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('identity.me');
    Route::patch('me', [AuthController::class, 'updateMe'])->name('identity.me.update');
    // A new email or mobile number, confirmed by a code sent to it.
    Route::post('me/contact', [ContactController::class, 'send'])->middleware('throttle:10,1')->name('identity.contact.send');
    Route::post('me/contact/confirm', [ContactController::class, 'confirm'])->middleware('throttle:20,1')->name('identity.contact.confirm');
    Route::get('me/sessions', [SessionsController::class, 'index'])->name('identity.sessions');
    Route::delete('me/sessions/{session}', [SessionsController::class, 'destroy'])->whereNumber('session')->name('identity.sessions.end');
    Route::get('me/social', [SocialSignInController::class, 'mine'])->name('identity.social.mine');
    Route::middleware('throttle:20,1')->group(function (): void {
        Route::post('me/social', [SocialSignInController::class, 'link'])->name('identity.social.link');
        Route::post('me/social/ticket', [SocialSignInController::class, 'linkTicket'])->name('identity.social.link-ticket');
        Route::post('me/social/{provider}/start', [SocialSignInController::class, 'startLink'])->whereIn('provider', ['google', 'apple'])->name('identity.social.link-start');
    });
    Route::delete('me/social/{provider}', [SocialSignInController::class, 'unlink'])->whereIn('provider', ['google', 'apple'])->name('identity.social.unlink');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('identity.logout');
    Route::get('realtime', [RealtimeController::class, 'show'])->name('identity.realtime');
    Route::post('realtime/auth', [RealtimeController::class, 'auth'])->name('identity.realtime.auth');
});
