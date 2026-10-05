<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\V1\Controllers\AuthController;

// Mounted at /api/v1 with the api, tenant and locale middleware (IdentityServiceProvider).

Route::post('auth/login', [AuthController::class, 'login'])->name('identity.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('identity.me');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('identity.logout');
});
