<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\V1\Controllers\AccountsController;
use Modules\Identity\Http\V1\Controllers\SocialSignInController;

// Mounted at /api/v1 WITHOUT the tenant middleware: the shared app finds the
// person's accounts before it knows any academy (IdentityServiceProvider).

Route::post('accounts/code', [AccountsController::class, 'send'])->name('identity.accounts.code');
Route::post('accounts/code/verify', [AccountsController::class, 'verify'])->name('identity.accounts.verify');
Route::post('accounts/social', [SocialSignInController::class, 'discover'])->middleware('throttle:20,1')->name('identity.accounts.social');
