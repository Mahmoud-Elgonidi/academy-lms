<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\V1\Controllers\SiteController;

// The academy's public page (the portal reads it on the academy's host), read and written before anyone signs in.
Route::get('site', [SiteController::class, 'show'])->middleware('throttle:120,1')->name('site.show');
Route::post('site/requests', [SiteController::class, 'store'])->middleware('throttle:5,60')->name('site.requests');
