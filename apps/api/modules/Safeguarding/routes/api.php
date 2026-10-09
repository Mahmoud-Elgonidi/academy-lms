<?php

use Illuminate\Support\Facades\Route;
use Modules\Safeguarding\Http\V1\Controllers\ConcernsController;

// The family and teacher apps: telling the academy's safeguarding leads that something is wrong (the same as "Tell us" in /my and "Raise a concern" in /teach).
Route::middleware('auth:sanctum')->prefix('safeguarding')->group(function () {
    Route::get('new', [ConcernsController::class, 'options'])->name('safeguarding.new');
    Route::get('concerns', [ConcernsController::class, 'index'])->name('safeguarding.concerns');
    Route::post('concerns', [ConcernsController::class, 'store'])->middleware(['idempotent', 'throttle:10,1'])->name('safeguarding.concerns.store');
});
