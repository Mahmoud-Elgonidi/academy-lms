<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\V1\Controllers\SiteController;

// Mounted at /api/v1 WITHOUT the tenant middleware: the platform's own page,
// read before anyone signs in and on no academy's address (PlatformServiceProvider).

Route::get('site/platform', [SiteController::class, 'show'])->middleware('throttle:120,1')->name('platform.site');
Route::post('site/platform/requests', [SiteController::class, 'store'])->middleware('throttle:5,60')->name('platform.site.requests');
