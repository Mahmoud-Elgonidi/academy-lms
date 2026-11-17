<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\V1\Controllers\SiteController;

// The academy's public page (the portal reads it on the academy's host), read and written before anyone signs in.
Route::get('site', [SiteController::class, 'show'])->middleware('throttle:120,1')->name('site.show');
// The builder's live preview of the draft: a short token from /admin is the proof (the portal's server calls without the admin's session).
Route::get('site/preview', [SiteController::class, 'preview'])->middleware('throttle:60,1')->name('site.preview');
Route::post('site/requests', [SiteController::class, 'store'])->middleware('throttle:5,60')->name('site.requests');
