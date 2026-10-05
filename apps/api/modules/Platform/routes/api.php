<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\V1\Controllers\BrandingController;

Route::get('branding', [BrandingController::class, 'show'])->name('platform.branding');
