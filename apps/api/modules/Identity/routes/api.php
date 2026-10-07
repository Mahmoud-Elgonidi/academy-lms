<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\V1\Controllers\AccountsController;
use Modules\Identity\Http\V1\Controllers\AuthController;
use Modules\Identity\Http\V1\Controllers\CodeSignInController;
use Modules\Identity\Http\V1\Controllers\SessionsController;

// Mounted at /api/v1 with the api, tenant and locale middleware (IdentityServiceProvider).

Route::post('auth/login', [AuthController::class, 'login'])->name('identity.login');
Route::post('auth/code', [CodeSignInController::class, 'send'])->name('identity.code.send');
Route::post('auth/code/verify', [CodeSignInController::class, 'verify'])->name('identity.code.verify');
Route::post('accounts/sign-in', [AccountsController::class, 'signIn'])->name('identity.accounts.sign-in');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('identity.me');
    Route::patch('me', [AuthController::class, 'updateMe'])->name('identity.me.update');
    Route::get('me/sessions', [SessionsController::class, 'index'])->name('identity.sessions');
    Route::delete('me/sessions/{session}', [SessionsController::class, 'destroy'])->whereNumber('session')->name('identity.sessions.end');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('identity.logout');
});
